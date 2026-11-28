<?php

declare(strict_types=1);

namespace AIArmada\References\Persistence;

use Closure;
use Illuminate\Database\Eloquent\Model;

abstract class ReferencePersistence extends Model
{
    public function save(array $options = []): bool
    {
        return $this->saveReference(fn (): bool => parent::save($options));
    }

    public function saveOrIgnore(array $options = [], array | string | null $uniqueBy = null): bool
    {
        return $this->saveReference(fn (): bool => parent::saveOrIgnore($options, $uniqueBy));
    }

    /**
     * @param  Closure(): bool  $save
     */
    abstract protected function saveReference(Closure $save): bool;
}
