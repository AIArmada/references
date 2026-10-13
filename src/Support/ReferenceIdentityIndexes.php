<?php

declare(strict_types=1);

namespace AIArmada\References\Support;

use AIArmada\CommerceSupport\Support\ConnectionDriver;
use Illuminate\Database\Schema\Blueprint;
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

        if (in_array($driver, ['mysql', 'mariadb'], true)) {
            return;
        }

        self::partial(
            $tableName,
            self::name($tableName, $identityColumn) . '_owner_unique',
            ['owner_type', 'owner_id', $identityColumn],
            self::wrap('owner_type') . ' IS NOT NULL AND ' . self::wrap('owner_id') . ' IS NOT NULL AND ' . self::wrap($identityColumn) . ' IS NOT NULL',
        );
        self::partial(
            $tableName,
            self::name($tableName, $identityColumn) . '_global_unique',
            [$identityColumn],
            self::wrap('owner_type') . ' IS NULL AND ' . self::wrap('owner_id') . ' IS NULL AND ' . self::wrap($identityColumn) . ' IS NOT NULL',
        );
    }

    public static function addOwnerIndexesToBlueprint(Blueprint $table, string $tableName, string $identityColumn): void
    {
        $driver = self::driver();
        if (! in_array($driver, ['mysql', 'mariadb'], true)) {
            return;
        }

        $grammar = Schema::getConnection()->getQueryGrammar();
        $base = self::name($tableName, $identityColumn);
        foreach (['owner', 'global'] as $scope) {
            $columns = $scope === 'owner'
                ? ['owner_type', 'owner_id', $identityColumn]
                : [$identityColumn];
            $conditions = $scope === 'owner'
                ? [$grammar->wrap('owner_type') . ' IS NOT NULL', $grammar->wrap('owner_id') . ' IS NOT NULL']
                : [$grammar->wrap('owner_type') . ' IS NULL', $grammar->wrap('owner_id') . ' IS NULL'];
            $packed = 'CONCAT(' . implode(', ', array_merge(...array_map(
                static fn (string $column): array => [sprintf('CHAR_LENGTH(%s)', $grammar->wrap($column)), "':'", $grammar->wrap($column), "'|'"],
                $columns,
            ))) . ')';
            $indexName = $base . '_' . $scope . '_unique';
            $packedColumn = $indexName . '_packed';
            $identityHashColumn = $indexName . '_identity';
            $table->text($packedColumn)->nullable()->virtualAs($packed)->invisible();
            $expression = sprintf('CASE WHEN %s THEN SHA2(%s, 256) ELSE NULL END', implode(' AND ', $conditions), $grammar->wrap($packedColumn));
            $table->string($identityHashColumn, 64)->nullable()->virtualAs($expression)->invisible();
            $table->unique($identityHashColumn, $indexName);
        }
    }

    /**
     * @param  list<string>  $columns
     */
    private static function partial(string $tableName, string $indexName, array $columns, string $predicate): void
    {
        if (! Schema::hasTable($tableName) || Schema::hasIndex($tableName, $indexName)) {
            return;
        }

        $connection = Schema::getConnection();
        $grammar = $connection->getQueryGrammar();

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

        if (! in_array($driver, ['mysql', 'mariadb', 'pgsql', 'sqlite'], true)) {
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
