---
title: Configuration
---

# Configuration

`config/references.php` controls the references table name, JSON column type, and slug generation defaults.

## Database

```php
'database' => [
    'table_prefix' => $tablePrefix,
    'json_column_type' => env('REFERENCES_JSON_COLUMN_TYPE', 'jsonb'),
    'tables' => [
        'references' => env('REFERENCES_TABLE_REFERENCES', $tablePrefix . 'references'),
    ],
],
```

- `database.table_prefix` controls the default `ref_` prefix
- `database.json_column_type` sets the JSON column type used by migrations
- `database.tables.references` can override the table name entirely

## Slug

```php
'slug' => [
    'source' => env('REFERENCES_SLUG_SOURCE', 'title'),
    'max_length' => (int) env('REFERENCES_SLUG_MAX_LENGTH', 200),
],
```

- `slug.source` selects the model attribute used to generate slugs
- `slug.max_length` caps the generated slug length
