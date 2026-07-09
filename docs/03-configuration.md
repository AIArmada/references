---
title: Configuration
---

# Configuration

`config/references.php` controls the references table name, JSON column type, and slug generation defaults.

## Database

```php
'database' => [
    'table_prefix' => '',
    'json_column_type' => env('REFERENCES_JSON_COLUMN_TYPE', 'jsonb'),
    'tables' => [
        'references' => env('REFERENCES_TABLE_REFERENCES', 'references'),
    ],
],
```

- `database.table_prefix` defaults to an empty string (table name `references` unless overridden)
- Set a prefix such as `ref_` if you need namespaced table names in a shared database
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

## Media

Media collections are registered on `Reference` and use the disk from `config('media-library.disk_name')`. Accepted mime types are JPEG, PNG, and WebP, with responsive images enabled.

| Collection | Cardinality |
| --- | --- |
| `front_cover` | single file |
| `back_cover` | single file |
| `gallery` | multiple files |
