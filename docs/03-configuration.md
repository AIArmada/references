---
title: Configuration
---

# Configuration

`config/references.php` controls the references table name, JSON column type, owner boundary, and slug generation defaults.

## Database

```php
'database' => [
    'table_prefix' => '',
    'json_column_type' => env('REFERENCES_JSON_COLUMN_TYPE', 'jsonb'),
    'tables' => [
        'references' => env('REFERENCES_TABLE_REFERENCES', $tablePrefix . 'references'),
    ],
],
```

- `database.table_prefix` defaults to an empty string (table name `references` unless overridden)
- Set a prefix such as `ref_` if you need namespaced table names in a shared database
- `database.json_column_type` defaults to `jsonb`; migrations resolve it through the `commerce_json_column_type('references', 'jsonb')` helper, which prefers `REFERENCES_JSON_COLUMN_TYPE`, then `COMMERCE_JSON_COLUMN_TYPE`, then the config value
- `database.tables.references` can override the table name entirely

## Owner scoping

```php
'owner' => [
    'enabled' => env('REFERENCES_OWNER_ENABLED', true),
    'include_global' => env('REFERENCES_OWNER_INCLUDE_GLOBAL', false),
    'auto_assign_on_create' => env('REFERENCES_OWNER_AUTO_ASSIGN_ON_CREATE', true),
],
```

- `owner.enabled` enables the shared `commerce-support` owner scope
- `owner.include_global` must be enabled explicitly when owner queries should include global rows
- `owner.auto_assign_on_create` controls inheritance of the current owner for new references
- Bind `OwnerResolverInterface` in the host application and use `OwnerContext::withOwner()` for scoped work

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

Media collections are registered on `Reference` and use `config('references.media.disk')`. Accepted mime types are JPEG, PNG, and WebP, with responsive images enabled.

| Collection | Cardinality |
| --- | --- |
| `front_cover` | single file |
| `back_cover` | single file |
| `gallery` | multiple files |
