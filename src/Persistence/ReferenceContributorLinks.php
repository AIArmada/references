<?php

declare(strict_types=1);

namespace AIArmada\References\Persistence;

use AIArmada\References\Enums\ReferenceContributorRole;
use AIArmada\References\Models\Reference;
use AIArmada\References\Models\ReferenceContributor;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;
use RuntimeException;

final readonly class ReferenceContributorLinks
{
    private const int CHUNK_SIZE = 500;

    /**
     * @param  list<string>  $ids
     */
    public function deleteIds(Reference $reference, array $ids): void
    {
        if ($ids === []) {
            return;
        }

        $model = $this->model($reference);
        $deleted = 0;

        foreach (array_chunk($ids, self::CHUNK_SIZE) as $chunk) {
            $deleted += $model->newQueryWithoutScopes()
                ->where('reference_id', $reference->getKey())
                ->whereKey($chunk)
                ->delete();
        }

        if ($deleted !== count($ids)) {
            throw new RuntimeException('Contributor deletion did not remove the expected rows.');
        }
    }

    /**
     * @param  list<string>  $ids
     */
    public function insertIds(Reference $reference, ReferenceContributorRole $role, string $type, array $ids): void
    {
        if ($ids === []) {
            return;
        }

        $model = $this->model($reference);
        $now = CarbonImmutable::now();
        $referenceId = (string) $reference->getKey();

        foreach (array_chunk($ids, self::CHUNK_SIZE) as $chunk) {
            $rows = [];

            foreach ($chunk as $id) {
                $rows[] = [
                    'id' => (string) Str::uuid(),
                    'reference_id' => $referenceId,
                    'contributor_type' => $type,
                    'contributor_id' => $id,
                    'role' => $role->value,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }

            $inserted = $model->newQueryWithoutScopes()->toBase()->insert($rows);

            if ($inserted !== true) {
                throw new RuntimeException('Contributor creation did not insert the expected rows.');
            }
        }
    }

    private function model(Reference $reference): ReferenceContributor
    {
        $model = new ReferenceContributor;
        $model->setConnection($reference->getConnectionName());

        return $model;
    }
}
