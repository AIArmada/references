<?php

declare(strict_types=1);

$tablePrefix = '';

return [
    'database' => [
        'table_prefix' => $tablePrefix,
        'json_column_type' => env('REFERENCES_JSON_COLUMN_TYPE', 'jsonb'),
        'tables' => [
            'references' => env('REFERENCES_TABLE_REFERENCES', $tablePrefix . 'references'),
        ],
    ],
    'owner' => [
        'enabled' => (bool) env('REFERENCES_OWNER_ENABLED', true),
        'include_global' => (bool) env('REFERENCES_OWNER_INCLUDE_GLOBAL', false),
        'auto_assign_on_create' => (bool) env('REFERENCES_OWNER_AUTO_ASSIGN_ON_CREATE', true),
    ],
    'slug' => [
        'source' => env('REFERENCES_SLUG_SOURCE', 'title'),
        'max_length' => (int) env('REFERENCES_SLUG_MAX_LENGTH', 200),
    ],
    'media' => [
        'disk' => 'public',
    ],
];
