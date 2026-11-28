<?php

declare(strict_types=1);

namespace AIArmada\References\Actions;

use AIArmada\References\Enums\ReferenceContributorRole;
use AIArmada\References\Enums\ReferenceRecordKind;
use AIArmada\References\Models\Reference;
use AIArmada\References\Models\ReferenceContributor;
use Illuminate\Support\Str;
use InvalidArgumentException;

final readonly class GuardReferenceContributorWrite
{
    public function __construct(
        private LockReferenceForWrite $lock = new LockReferenceForWrite,
        private ValidateContributorIdentity $identity = new ValidateContributorIdentity,
    ) {}

    public function saving(ReferenceContributor $contributor): void
    {
        $this->assertRequiredAttributes($contributor);

        $reference = $this->lockReference($contributor);

        $role = $contributor->role;

        if (! $role instanceof ReferenceContributorRole) {
            throw new InvalidArgumentException('Reference contributor role is required.');
        }

        if ($contributor->exists && $contributor->isDirty(['contributor_type', 'contributor_id', 'role'])) {
            throw new InvalidArgumentException('Replace contributor identities through SyncReferenceContributors.');
        }

        if ($role === ReferenceContributorRole::Author && $reference->record_kind !== ReferenceRecordKind::Work) {
            throw new InvalidArgumentException('Authors can only be stored on works.');
        }

        $targets = $this->identity->canonicalizeTargets(
            $contributor->contributor_type,
            [$contributor->contributor_id],
            $contributor->getConnection()->getName(),
        );

        $contributor->contributor_type = $targets['type'];
        $contributor->contributor_id = $targets['ids'][0];
    }

    public function deleting(ReferenceContributor $contributor): void
    {
        $this->assertReferenceId($contributor);
        $this->lockReference($contributor);
    }

    private function assertRequiredAttributes(ReferenceContributor $contributor): void
    {
        $this->assertReferenceId($contributor);
        $attributes = $contributor->getAttributes();

        $type = $attributes['contributor_type'] ?? null;

        if (! is_string($type) || mb_trim($type) === '') {
            throw new InvalidArgumentException('Reference contributor type must identify a concrete Eloquent model.');
        }

        $contributorId = $attributes['contributor_id'] ?? null;

        if (! is_string($contributorId) || mb_trim($contributorId) === '') {
            throw new InvalidArgumentException('Reference contributor IDs must be UUIDs.');
        }

        $role = $attributes['role'] ?? null;

        if (! is_string($role) || ReferenceContributorRole::tryFrom($role) === null) {
            throw new InvalidArgumentException('Reference contributor role is required.');
        }
    }

    private function assertReferenceId(ReferenceContributor $contributor): void
    {
        $referenceId = $contributor->getAttributes()['reference_id'] ?? null;

        if (! is_string($referenceId) || ! Str::isUuid(mb_trim($referenceId))) {
            throw new InvalidArgumentException('Reference contributor reference_id must be a UUID.');
        }

        $contributor->reference_id = mb_strtolower(mb_trim($referenceId));
    }

    private function lockReference(ReferenceContributor $contributor): Reference
    {
        if ($contributor->exists && $contributor->isDirty(['id', 'reference_id'])) {
            throw new InvalidArgumentException('Contributor link identity is immutable.');
        }

        $reference = new Reference;
        $reference->setConnection($contributor->getConnectionName());
        $reference->setAttribute($reference->getKeyName(), $contributor->reference_id);

        return $this->lock->handle($reference);
    }
}
