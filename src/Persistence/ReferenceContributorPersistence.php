<?php

declare(strict_types=1);

namespace AIArmada\References\Persistence;

use AIArmada\References\Actions\GuardReferenceContributorWrite;
use Illuminate\Database\Eloquent\Model;

abstract class ReferenceContributorPersistence extends Model
{
    public function save(array $options = []): bool
    {
        return $this->getConnection()->transaction(function () use ($options): bool {
            (new GuardReferenceContributorWrite)->saving($this);

            return parent::save($options);
        });
    }

    public function saveOrIgnore(array $options = [], array | string | null $uniqueBy = null): bool
    {
        return $this->getConnection()->transaction(function () use ($options, $uniqueBy): bool {
            (new GuardReferenceContributorWrite)->saving($this);

            return parent::saveOrIgnore($options, $uniqueBy);
        });
    }

    public function delete(): ?bool
    {
        if (! $this->exists) {
            return parent::delete();
        }

        return $this->getConnection()->transaction(function (): ?bool {
            (new GuardReferenceContributorWrite)->deleting($this);

            return parent::delete();
        });
    }
}
