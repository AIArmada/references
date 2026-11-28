<?php

declare(strict_types=1);

namespace AIArmada\References\Actions;

use AIArmada\References\Models\Reference;
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Validation\ValidationException;

final readonly class SaveReference
{
    public function __construct(
        private LockReferenceForWrite $lock = new LockReferenceForWrite,
        private LockReferenceTree $tree = new LockReferenceTree,
    ) {}

    /**
     * @param  Closure(): bool  $save
     */
    public function handle(Reference $reference, Closure $save): bool
    {
        return $reference->getConnection()->transaction(function () use ($reference, $save): bool {
            $hierarchyChanged = ! $reference->exists || $reference->isDirty(['parent_id', 'record_kind']);
            $lockedRoots = [];

            if ($reference->exists && $hierarchyChanged) {
                $lockedRoots = $this->tree->lockForHierarchy(
                    $reference,
                    $this->parentId($reference, true),
                    $this->parentId($reference, false),
                );
            }

            $locked = null;

            if ($reference->exists) {
                $locked = $this->lock->handle($reference);

                foreach (['record_kind', 'parent_id'] as $attribute) {
                    if ($reference->getRawOriginal($attribute) !== $locked->getRawOriginal($attribute)) {
                        throw ValidationException::withMessages([$attribute => 'The reference hierarchy has changed. Reload the reference before saving.']);
                    }
                }

                /** @var CarbonImmutable|null $originalPublishedAt */
                $originalPublishedAt = $reference->getOriginal('published_at');

                if ($reference->getRawOriginal('status') !== $locked->getRawOriginal('status')
                    || $originalPublishedAt?->format('U.u') !== $locked->published_at?->format('U.u')) {
                    throw ValidationException::withMessages(['status' => 'The reference publication state has changed. Reload the reference before saving.']);
                }

                if ($hierarchyChanged) {
                    $this->tree->assertRootLocked($reference, $this->parentId($locked), $lockedRoots);
                    $this->tree->assertRootLocked($reference, $this->parentId($reference), $lockedRoots);
                }
            } else {
                $parentId = $this->parentId($reference);
                $this->tree->assertUuidParent($parentId);

                if ($parentId !== null) {
                    $lockedRoots = $this->tree->lockForHierarchy($reference, null, $parentId);
                    $this->tree->assertRootLocked($reference, $parentId, $lockedRoots);
                }
            }

            $reference->prepareForPersistence();

            return $save();
        });
    }

    private function parentId(Reference $reference, bool $original = false): ?string
    {
        $value = $original
            ? $reference->getRawOriginal('parent_id')
            : $reference->getAttribute('parent_id');

        return is_string($value) && $value !== '' ? $value : null;
    }
}
