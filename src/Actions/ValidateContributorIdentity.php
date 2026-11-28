<?php

declare(strict_types=1);

namespace AIArmada\References\Actions;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Str;
use InvalidArgumentException;
use ReflectionClass;
use Throwable;

final readonly class ValidateContributorIdentity
{
    private const int CHUNK_SIZE = 500;

    public function type(string $type): string
    {
        $type = mb_trim($type);

        if ($type === '') {
            throw new InvalidArgumentException('Reference contributor type must identify a concrete Eloquent model.');
        }

        $class = Relation::getMorphedModel($type) ?? $type;
        $class = mb_ltrim($class, '\\');

        if (! class_exists($class)) {
            throw new InvalidArgumentException('Reference contributor type must identify a concrete Eloquent model.');
        }

        $reflection = new ReflectionClass($class);

        if (! $reflection->isSubclassOf(Model::class) || ! $reflection->isInstantiable()) {
            throw new InvalidArgumentException('Reference contributor type must identify a concrete Eloquent model.');
        }

        try {
            /** @var Model $model */
            $model = $reflection->newInstanceWithoutConstructor();

            return $model->getMorphClass();
        } catch (Throwable $exception) {
            throw new InvalidArgumentException('Reference contributor type must identify a concrete Eloquent model.', previous: $exception);
        }
    }

    public function id(string $id): string
    {
        $id = mb_strtolower(mb_trim($id));

        if (! Str::isUuid($id)) {
            throw new InvalidArgumentException('Reference contributor IDs must be UUIDs.');
        }

        return $id;
    }

    /**
     * @param  list<string>  $ids
     * @return array{type: string, ids: list<string>}
     */
    public function canonicalizeTargets(string $type, array $ids, ?string $connection = null): array
    {
        $type = $this->type($type);
        $normalized = [];

        foreach ($ids as $id) {
            $normalized[$this->id($id)] = true;
        }

        $normalized = array_keys($normalized);

        if ($normalized === []) {
            return ['type' => $type, 'ids' => []];
        }

        $class = Relation::getMorphedModel($type) ?? $type;
        $reflection = new ReflectionClass($class);

        try {
            /** @var Model $model */
            $model = $reflection->newInstance();
        } catch (Throwable $exception) {
            throw new InvalidArgumentException('Reference contributor type must identify a constructible Eloquent model.', previous: $exception);
        }

        if ($model->getConnectionName() === null && $connection !== null) {
            $model->setConnection($connection);
        }

        $found = [];

        foreach (array_chunk($normalized, self::CHUNK_SIZE) as $chunk) {
            foreach ($model->newQuery()->whereKey($chunk)->pluck($model->getKeyName())->all() as $key) {
                $found[mb_strtolower((string) $key)] = true;
            }
        }

        foreach ($normalized as $id) {
            if (! isset($found[$id])) {
                throw new InvalidArgumentException('Reference contributor target is missing or outside the current owner scope: ' . $id);
            }
        }

        return ['type' => $type, 'ids' => $normalized];
    }
}
