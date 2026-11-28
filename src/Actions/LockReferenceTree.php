<?php

declare(strict_types=1);

namespace AIArmada\References\Actions;

use AIArmada\References\Models\Reference;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final readonly class LockReferenceTree
{
    /**
     * @return array<string, true>
     */
    public function lockForHierarchy(Reference $reference, ?string $originalParentId, ?string $newParentId): array
    {
        $rootIds = [];
        $selfKey = $reference->exists ? (string) $reference->getKey() : null;

        if ($originalParentId === null) {
            if ($selfKey !== null) {
                $rootIds[$selfKey] = true;
            }
        } else {
            $rootIds[$this->rootId($reference, $originalParentId)] = true;
        }

        if ($newParentId === null) {
            if ($selfKey !== null) {
                $rootIds[$selfKey] = true;
            }
        } else {
            $rootIds[$this->rootId($reference, $newParentId)] = true;
        }

        $this->lockRoots($reference, array_keys($rootIds));

        return $rootIds;
    }

    /**
     * @return array<string, true>
     */
    public function lockTreeForParent(Reference $reference, ?string $parentId): array
    {
        $selfKey = (string) $reference->getKey();
        $rootId = $parentId === null ? $selfKey : $this->rootId($reference, $parentId);

        $this->lockRoots($reference, [$rootId]);

        return [$rootId => true];
    }

    /**
     * @param  array<string, true>  $lockedRoots
     */
    public function assertRootLocked(Reference $reference, ?string $parentId, array $lockedRoots): void
    {
        $actualRoot = $parentId === null
            ? (string) $reference->getKey()
            : $this->rootId($reference, $parentId);

        if (! isset($lockedRoots[$actualRoot])) {
            throw ValidationException::withMessages([
                'parent_id' => 'The reference hierarchy has changed. Reload the reference before saving.',
            ]);
        }
    }

    public function assertUuidParent(?string $parentId): void
    {
        if ($parentId !== null && ! Str::isUuid($parentId)) {
            throw ValidationException::withMessages([
                'parent_id' => 'Invalid parent_id: must be a UUID.',
            ]);
        }
    }

    /**
     * @param  list<string>  $rootIds
     */
    private function lockRoots(Reference $reference, array $rootIds): void
    {
        $rootIds = array_values(array_unique($rootIds));

        if ($rootIds === []) {
            return;
        }

        $locked = $this->query($reference)
            ->whereKey($rootIds)
            ->orderBy('id')
            ->lockForUpdate()
            ->get();

        if ($locked->count() !== count($rootIds)) {
            throw ValidationException::withMessages([
                'parent_id' => 'Invalid parent_id: reference not found or inaccessible.',
            ]);
        }
    }

    private function rootId(Reference $reference, string $nodeId): string
    {
        if (! Str::isUuid($nodeId)) {
            throw ValidationException::withMessages([
                'parent_id' => 'Invalid parent_id: must be a UUID.',
            ]);
        }

        $seen = [];
        $cursorId = $nodeId;

        while (true) {
            if (isset($seen[$cursorId])) {
                throw ValidationException::withMessages([
                    'parent_id' => 'Invalid parent_id: existing hierarchy contains a cycle.',
                ]);
            }

            $seen[$cursorId] = true;

            $cursor = $this->query($reference)->whereKey($cursorId)->first(['id', 'parent_id']);

            if ($cursor === null) {
                throw ValidationException::withMessages([
                    'parent_id' => 'Invalid parent_id: reference not found or inaccessible.',
                ]);
            }

            $parentId = $this->parentId($cursor);

            if ($parentId === null) {
                return (string) $cursor->getKey();
            }

            $cursorId = $parentId;
        }
    }

    private function parentId(Reference $reference): ?string
    {
        $parentId = $reference->getAttribute('parent_id');

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
