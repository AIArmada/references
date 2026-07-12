<?php

declare(strict_types=1);

$tablePrefix = '';

return [
    'database' => [
        'table_prefix' => $tablePrefix,
        'tables' => [
            'references' => env('REFERENCES_TABLE_REFERENCES', $tablePrefix . 'references'),
        ],
    ],
    'slug' => [
        'source' => env('REFERENCES_SLUG_SOURCE', 'title'),
        'max_length' => (int) env('REFERENCES_SLUG_MAX_LENGTH', 200),
    ],
];
