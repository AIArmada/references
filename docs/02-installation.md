---
title: Installation
---

# Installation

## Requirements

- PHP 8.4+
- Laravel 13+
- `spatie/laravel-sluggable`

## Install

```bash
composer require aiarmada/references
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
