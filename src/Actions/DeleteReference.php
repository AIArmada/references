<?php

declare(strict_types=1);

namespace AIArmada\References\Actions;

use AIArmada\CommerceSupport\Data\OwnerScopeConfig;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\References\Models\Reference;
use AIArmada\References\Models\ReferenceContributor;
use Closure;
use Illuminate\Database\Connection;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Spatie\MediaLibrary\MediaCollections\Filesystem;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Throwable;

final readonly class DeleteReference
{
    private const int CHUNK_SIZE = 500;

    public function __construct(
        private LockReferenceForWrite $lock = new LockReferenceForWrite,
        private LockReferenceTree $tree = new LockReferenceTree,
    ) {}

    /**
     * @param  Closure(Reference): bool  $deleting
     * @param  Closure(Reference): mixed|null  $deleted
     */
    public function handle(Reference $reference, Closure $deleting, ?Closure $deleted): ?bool
    {
        if (! $reference->exists) {
            return null;
        }

        $preserveMedia = $reference->shouldDeletePreservingMedia();
        $connection = $reference->getConnection();

        try {
            return $connection->transaction(function () use ($reference, $deleting, $deleted, $preserveMedia, $connection): bool {
                $lockedRoots = $this->tree->lockTreeForParent($reference, $this->persistedParentId($reference));
                $locked = $this->lock->handle($reference);
                $this->tree->assertRootLocked($locked, $this->parentId($locked), $lockedRoots);
                $reference->setRawAttributes($locked->getAttributes(), true);

                if (! $reference->withoutMediaCascadeDeletion(fn (): bool => $deleting($reference))) {
                    return false;
                }

                $descendants = $this->lockDescendants($reference);
                $config = Reference::ownerScopeConfig();
                $active = $this->activeOwnerContext($config);
                $contexts = [];
                $owners = [];

                $contexts[(string) $reference->getKey()] = $this->ownerContext($reference, $config, $active, $owners);

                foreach ($descendants as $descendant) {
                    $contexts[(string) $descendant->getKey()] = $this->ownerContext($descendant, $config, $active, $owners);
                }

                foreach (array_reverse($descendants) as $descendant) {
                    $this->runInOwnerContext($contexts[(string) $descendant->getKey()], $active['key'], function () use ($descendant, $deleting): void {
                        if (! $descendant->withoutMediaCascadeDeletion(fn (): bool => $deleting($descendant))) {
                            throw new RuntimeException('Reference descendant deletion was cancelled.');
                        }
                    });
                }

                $records = [...$descendants, $reference];
                $ids = array_map(static fn (Reference $record): string => (string) $record->getKey(), $records);
                $media = [];
                $mediaModel = $preserveMedia ? null : $this->mediaModel($reference);

                if ($mediaModel !== null) {
                    $media = $this->mediaFor($reference, $mediaModel, $ids);
                    $this->deleteMedia($mediaModel, $media);
                }

                $this->deleteContributors($reference, $ids);
                $this->deleteReferenceRows($reference, $ids);

                foreach ($records as $record) {
                    $record->exists = false;
                }

                $ordered = [...array_reverse($descendants), $reference];

                if ($deleted !== null || $media !== []) {
                    $this->afterCommit($connection, function () use ($media, $ordered, $contexts, $config, $deleted): void {
                        if ($deleted !== null) {
                            $activeKey = '';

                            try {
                                $activeKey = $this->activeOwnerKey($config);
                            } catch (Throwable $exception) {
                                report($exception);
                            }

                            foreach ($ordered as $record) {
                                $context = $contexts[(string) $record->getKey()] ?? ['key' => '', 'owner' => null];

                                try {
                                    $this->runInOwnerContext($context, $activeKey, static fn (): mixed => $deleted($record));
                                } catch (Throwable $exception) {
                                    report($exception);
                                }
                            }
                        }

                        try {
                            $this->removeMediaFiles($media);
                        } catch (Throwable $exception) {
                            report($exception);
                        }
                    });
                }

                return true;
            });
        } catch (Throwable $exception) {
            $reference->exists = true;

            try {
                if (! $this->query($reference)->whereKey($reference->getKey())->exists()) {
                    $reference->exists = false;
                }
            } catch (Throwable) {
                $reference->exists = true;
            }

            throw $exception;
        }
    }

    /**
     * @return list<Reference>
     */
    private function lockDescendants(Reference $reference): array
    {
        $seen = [(string) $reference->getKey() => true];
        $frontier = [(string) $reference->getKey()];
        $descendants = [];

        while ($frontier !== []) {
            $next = [];

            foreach (array_chunk($frontier, self::CHUNK_SIZE) as $ids) {
                $children = $this->query($reference)
                    ->whereIn('parent_id', $ids)
                    ->orderBy('id')
                    ->lockForUpdate()
                    ->get();

                foreach ($children as $child) {
                    $id = (string) $child->getKey();

                    if (isset($seen[$id])) {
                        throw new RuntimeException('Cannot delete a cyclic reference hierarchy.');
                    }

                    $seen[$id] = true;
                    $next[] = $id;
                    $descendants[] = $child;
                }
            }

            $frontier = $next;
        }

        return $descendants;
    }

    /**
     * @param  array{key: string, owner: Model|null}  $context
     */
    private function runInOwnerContext(array $context, ?string $activeKey, Closure $callback): mixed
    {
        if ($context['key'] === '') {
            return $callback();
        }

        if ($context['key'] === $activeKey) {
            return $callback();
        }

        return OwnerContext::withOwner($context['owner'], $callback);
    }

    /**
     * @return array{key: string, owner: Model|null}
     */
    private function activeOwnerContext(OwnerScopeConfig $config): array
    {
        if (! $config->enabled) {
            return ['key' => '', 'owner' => null];
        }

        $owner = OwnerContext::resolveConfigured($config);

        if ($owner instanceof Model) {
            return ['key' => $owner->getMorphClass() . ':' . (string) $owner->getKey(), 'owner' => $owner];
        }

        return ['key' => OwnerContext::isExplicitGlobal() ? 'global' : '', 'owner' => null];
    }

    /**
     * @param  array{key: string, owner: Model|null}  $active
     * @param  array<string, Model>  $owners
     * @return array{key: string, owner: Model|null}
     */
    private function ownerContext(Reference $reference, OwnerScopeConfig $config, array $active, array &$owners): array
    {
        if (! $config->enabled) {
            return ['key' => '', 'owner' => null];
        }

        $ownerType = $reference->getAttribute($config->ownerTypeColumn);
        $ownerId = $reference->getAttribute($config->ownerIdColumn);

        if (($ownerType === null) !== ($ownerId === null)) {
            throw new RuntimeException('Reference owner tuple is malformed: owner type and owner id must both be present or both be null.');
        }

        if ($ownerType === null) {
            return ['key' => 'global', 'owner' => null];
        }

        $key = (string) $ownerType . ':' . (string) $ownerId;

        if ($key === $active['key']) {
            return ['key' => $key, 'owner' => $active['owner']];
        }

        if (isset($owners[$key])) {
            return ['key' => $key, 'owner' => $owners[$key]];
        }

        try {
            $owner = OwnerContext::fromTypeAndIdOrFail((string) $ownerType, $ownerId);
        } catch (ModelNotFoundException $exception) {
            throw new RuntimeException('Reference owner row is missing for ' . $key . '.', previous: $exception);
        }

        if (! $owner instanceof Model) {
            throw new RuntimeException('Reference owner row is missing for ' . $key . '.');
        }

        $owners[$key] = $owner;

        return ['key' => $key, 'owner' => $owner];
    }

    private function activeOwnerKey(OwnerScopeConfig $config): ?string
    {
        if (! $config->enabled) {
            return null;
        }

        return $this->activeOwnerContext($config)['key'];
    }

    private function afterCommit(Connection $connection, Closure $callback): void
    {
        if ($connection->getName() === DB::getDefaultConnection()) {
            DB::afterCommit($callback);

            return;
        }

        $connection->afterCommit($callback);
    }

    /**
     * @param  list<string>  $ids
     * @return list<Media>
     */
    private function mediaFor(Reference $reference, Media $mediaModel, array $ids): array
    {
        $media = [];

        foreach (array_chunk($ids, self::CHUNK_SIZE) as $chunk) {
            foreach ($mediaModel->newQuery()
                ->where('model_type', $reference->getMorphClass())
                ->whereIn('model_id', $chunk)
                ->get() as $item) {
                $media[] = $item;
            }
        }

        return $media;
    }

    /**
     * @param  list<Media>  $media
     */
    private function deleteMedia(Media $mediaModel, array $media): void
    {
        if ($media === []) {
            return;
        }

        $ids = array_map(static fn (Media $item): mixed => $item->getKey(), $media);
        $deleted = 0;

        foreach (array_chunk($ids, self::CHUNK_SIZE) as $chunk) {
            $deleted += $mediaModel->newQuery()->whereKey($chunk)->delete();
        }

        if ($deleted !== count($media)) {
            throw new RuntimeException('Reference media deletion did not remove the expected rows.');
        }
    }

    private function mediaModel(Reference $reference): Media
    {
        $class = $reference->getMediaModel();

        if (! is_a($class, Media::class, true)) {
            throw new RuntimeException('The configured media model must extend ' . Media::class . '.');
        }

        /** @var Media $model */
        $model = new $class;

        if ($model->getConnectionName() === null) {
            $model->setConnection($reference->getConnectionName());
        }

        if ($model->getConnection()->getName() !== $reference->getConnection()->getName()) {
            throw new RuntimeException('Reference media rows must use the same database connection as the reference; cross-connection cascade deletion is not supported.');
        }

        return $model;
    }

    /**
     * @param  list<string>  $ids
     */
    private function deleteContributors(Reference $reference, array $ids): void
    {
        $contributor = new ReferenceContributor;
        $contributor->setConnection($reference->getConnectionName());

        foreach (array_chunk($ids, self::CHUNK_SIZE) as $chunk) {
            $contributor->newQueryWithoutScopes()
                ->whereIn('reference_id', $chunk)
                ->delete();
        }
    }

    /**
     * @param  list<string>  $ids
     */
    private function deleteReferenceRows(Reference $reference, array $ids): void
    {
        $deleted = 0;

        foreach (array_chunk($ids, self::CHUNK_SIZE) as $chunk) {
            $deleted += $reference->newQueryWithoutScopes()->whereKey($chunk)->delete();
        }

        if ($deleted !== count($ids)) {
            throw new RuntimeException('Reference deletion did not remove the expected rows.');
        }
    }

    /**
     * @param  list<Media>  $media
     */
    private function removeMediaFiles(array $media): void
    {
        $filesystem = app(Filesystem::class);

        foreach ($media as $item) {
            try {
                $filesystem->removeAllFiles($item);
            } catch (Throwable $exception) {
                report($exception);
            }
        }
    }

    private function parentId(Reference $reference): ?string
    {
        $parentId = $reference->getAttribute('parent_id');

        return is_string($parentId) && $parentId !== '' ? $parentId : null;
    }

    private function persistedParentId(Reference $reference): ?string
    {
        $parentId = $this->query($reference)->whereKey($reference->getKey())->value('parent_id');

        return is_string($parentId) && $parentId !== '' ? $parentId : null;
    }

    /**
     * @return Builder<Reference>
     */
    private function query(Reference $reference): Builder
    {
        $model = new Reference;
        $model->setConnection($reference->getConnectionName());

        return $model->newQueryWithoutScopes();
    }
}
