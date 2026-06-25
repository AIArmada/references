---
title: Troubleshooting
---

# Troubleshooting

## Slugs are duplicated

The package appends a numeric suffix when a slug already exists. If you need a different source field, change `REFERENCES_SLUG_SOURCE`.

## Parent references are not linked

`parent_id` is intentionally a plain reference column, not a database foreign key.

1. Confirm the parent reference exists before saving the child
2. Validate the parent in application logic
3. Make sure the parent and child use the same table prefix if you overrode the table name

## JSON data does not match the database

Set `REFERENCES_JSON_COLUMN_TYPE` to match your database driver.

## Tables are missing

Run the package migrations:

```bash
php artisan migrate
```

If you changed the table prefix, confirm the config and environment variables point to the same table name.
