---
title: Installation
---

# Installation

## Requirements

- PHP 8.4+
- Laravel 13+
- `spatie/laravel-sluggable` ^4
- `spatie/laravel-medialibrary` ^11

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
