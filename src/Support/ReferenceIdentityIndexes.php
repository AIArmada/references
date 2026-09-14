<?php

declare(strict_types=1);

namespace AIArmada\References\Support;

use AIArmada\CommerceSupport\Support\ConnectionDriver;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

/**
 * Owner/global partial unique indexes for reference identity columns.
 */
final class ReferenceIdentityIndexes
{
    public static function owner(string $tableName, string $identityColumn): void
    {
        $driver = self::driver();

        self::partial(
            $tableName,
            self::name($tableName, $identityColumn) . '_owner_unique',
            ['owner_type', 'owner_id', $identityColumn],
            self::wrap('owner_type') . ' IS NOT NULL AND ' . self::wrap('owner_id') . ' IS NOT NULL AND ' . self::wrap($identityColumn) . ' IS NOT NULL',
            $driver,
        );
        self::partial(
            $tableName,
            self::name($tableName, $identityColumn) . '_global_unique',
            [$identityColumn],
            self::wrap('owner_type') . ' IS NULL AND ' . self::wrap('owner_id') . ' IS NULL AND ' . self::wrap($identityColumn) . ' IS NOT NULL',
            $driver,
        );
    }

    /**
     * @param  list<string>  $columns
     */
    private static function partial(string $tableName, string $indexName, array $columns, string $predicate, string $driver): void
    {
        if (! Schema::hasTable($tableName) || Schema::hasIndex($tableName, $indexName)) {
            return;
        }

        $connection = Schema::getConnection();
        $grammar = $connection->getQueryGrammar();

        if ($driver === 'mysql') {
            $expressions = implode(', ', array_map(
                fn (string $column): string => sprintf(
                    '(CASE WHEN %s THEN %s ELSE NULL END)',
                    $predicate,
                    $grammar->wrap($column),
                ),
                $columns,
            ));

            $connection->statement(sprintf(
                'CREATE UNIQUE INDEX %s ON %s (%s)',
                $grammar->wrap($indexName),
                $grammar->wrapTable($tableName),
                $expressions,
            ));

            return;
        }

        $connection->statement(sprintf(
            'CREATE UNIQUE INDEX IF NOT EXISTS %s ON %s (%s) WHERE %s',
            $grammar->wrap($indexName),
            $grammar->wrapTable($tableName),
            implode(', ', array_map($grammar->wrap(...), $columns)),
            $predicate,
        ));
    }

    private static function driver(): string
    {
        $driver = ConnectionDriver::name(Schema::getConnection());

        if (! in_array($driver, ['mysql', 'pgsql', 'sqlite'], true)) {
            throw new RuntimeException(sprintf(
                'Reference identity migration cannot run on unsupported database driver [%s].',
                $driver,
            ));
        }

        return $driver;
    }

    private static function wrap(string $column): string
    {
        return Schema::getConnection()->getQueryGrammar()->wrap($column);
    }

    private static function name(string $tableName, string $suffix): string
    {
        return str_replace(['.', '-', ' '], '_', $tableName) . '_' . $suffix;
    }
}
