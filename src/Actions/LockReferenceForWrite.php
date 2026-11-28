<?php

declare(strict_types=1);

namespace AIArmada\References\Actions;

use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\References\Models\Reference;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Str;
use InvalidArgumentException;
use LogicException;

final readonly class LockReferenceForWrite
{
    public function handle(Reference $reference): Reference
    {
        if ($reference->getConnection()->transactionLevel() === 0) {
            throw new LogicException('Reference writes require a transaction.');
        }

        $key = $reference->getKey();

        if (! is_string($key) || ! Str::isUuid($key)) {
            throw new InvalidArgumentException('Reference primary key must be a UUID.');
        }

        if ($reference->exists && $reference->isDirty($reference->getKeyName())) {
            throw new InvalidArgumentException('Reference primary key is immutable.');
        }

        $locked = $reference->newQuery()->withoutOwnerScope()->whereKey($key)->lockForUpdate()->firstOrFail();
        $config = Reference::ownerScopeConfig();
        $ownerType = $locked->getAttribute($config->ownerTypeColumn);
        $ownerId = $locked->getAttribute($config->ownerIdColumn);

        if (($ownerType === null) !== ($ownerId === null)) {
            throw new AuthorizationException('Reference owner tuple is malformed: owner type and owner id must both be present or both be null.');
        }

        if (! $config->enabled) {
            return $locked;
        }

        $owner = OwnerContext::resolveConfigured($config);

        if ($ownerType === null) {
            if (! OwnerContext::isExplicitGlobal()) {
                throw new AuthorizationException('Explicit global context is required to write global references.');
            }

            return $locked;
        }

        if ($owner === null || ! $locked->belongsToOwner($owner)) {
            throw new AuthorizationException('Cross-owner reference write blocked.');
        }

        return $locked;
    }
}
