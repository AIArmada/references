<?php

declare(strict_types=1);

if (! function_exists('references_table')) {
    function references_table(string $key): string
    {
        return (string) config("references.database.tables.{$key}", $key);
    }
}
