---
title: Installation
---

# Installation

## Requirements

- PHP 8.5+
- Laravel 13+
- `aiarmada/commerce-support` (standalone installs must require it; owner context, morphs, and JSON helpers come from there)
- `spatie/laravel-sluggable` ^6
- `spatie/laravel-medialibrary` ^11
- PostgreSQL or SQLite (partial unique indexes enforce owner/global reference slug identity)

## Install

```bash
composer require aiarmada/references
```

Ensure Media Library is installed and its migrations are published if your app does not already use it:

```bash
php artisan vendor:publish --provider="Spatie\MediaLibrary\MediaLibraryServiceProvider" --tag="medialibrary-migrations"
php artisan migrate
```

## Run migrations

The service provider loads package migrations automatically.

```bash
php artisan migrate
```

## Publish configuration

```bash
php artisan vendor:publish --provider="AIArmada\References\ReferencesServiceProvider" --tag="references-config"
```

## Package verification

In the monorepo, run the package checks from the repository root:

```bash
composer --working-dir=packages/references arch:check
./vendor/bin/phpstan analyse packages/references/src --level=6
./vendor/bin/pest --parallel tests/src/References
```

`tools/package-check.php` resolves its package root from its own location. The architecture write check uses a package-local `vendor` when the package is installed standalone, otherwise the monorepo root `vendor`; it does not rely on the process working directory.
