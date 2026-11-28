<?php

declare(strict_types=1);

namespace AIArmada\References\Actions;

use AIArmada\References\Enums\ReferenceContributorRole;
use AIArmada\References\Enums\ReferenceRecordKind;
use AIArmada\References\Models\Reference;
use AIArmada\References\Persistence\ReferenceContributorLinks;
use InvalidArgumentException;

final readonly class SyncReferenceContributors
{
    public function __construct(
        private LockReferenceForWrite $lock = new LockReferenceForWrite,
        private ValidateContributorIdentity $identity = new ValidateContributorIdentity,
        private ReferenceContributorLinks $links = new ReferenceContributorLinks,
    ) {}

    /**
     * @param  list<string>  $contributorIds
     */
    public function handle(Reference $reference, ReferenceContributorRole | string $role, string $contributorType, array $contributorIds): Reference
    {
        $role = $role instanceof ReferenceContributorRole ? $role : ReferenceContributorRole::tryFrom($role);

        if ($role === null) {
            throw new InvalidArgumentException('Unknown reference contributor role.');
        }

        if (! $reference->exists) {
            throw new InvalidArgumentException('Contributors can only be synced on a persisted reference.');
        }

        $reference->getConnection()->transaction(function () use ($reference, $role, $contributorType, $contributorIds): void {
            $locked = $this->lock->handle($reference);

            if ($role === ReferenceContributorRole::Author && $locked->record_kind !== ReferenceRecordKind::Work) {
                throw new InvalidArgumentException('Authors can only be stored on works; editions and parts inherit them.');
            }

            $targets = $this->identity->canonicalizeTargets(
                $contributorType,
                array_values(array_filter($contributorIds, static fn (string $id): bool => mb_trim($id) !== '')),
                $locked->getConnection()->getName(),
            );
            $wanted = array_fill_keys($targets['ids'], true);
            $existing = $locked->contributorsForRole($role)->get();
            $remove = [];

            foreach ($existing as $contributor) {
                $id = mb_strtolower((string) $contributor->getKey());

                if ($contributor->contributor_type !== $targets['type'] || ! isset($wanted[$contributor->contributor_id])) {
                    $remove[] = $id;

                    continue;
                }

                unset($wanted[$contributor->contributor_id]);
            }

            $this->links->deleteIds($locked, $remove);
            $this->links->insertIds($locked, $role, $targets['type'], array_keys($wanted));
        });

        $reference->unsetRelation('contributors');

        return $reference;
    }
}
