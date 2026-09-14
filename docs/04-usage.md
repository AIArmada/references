---
title: Usage
---

# Usage

## Creating a reference

```php
use AIArmada\References\Enums\ReferenceStatus;
use AIArmada\References\Enums\ReferenceType;
use AIArmada\References\Models\Reference;
use AIArmada\CommerceSupport\Contracts\OwnerResolverInterface;
use AIArmada\CommerceSupport\Support\OwnerContext;

$owner = app(OwnerResolverInterface::class)->resolve();

$reference = OwnerContext::withOwner($owner, fn (): Reference => Reference::create([
    'type' => ReferenceType::Book,
    'status' => ReferenceStatus::Published,
    'title' => 'Sahih al-Bukhari',
    'author' => 'Imam al-Bukhari',
    'publisher' => 'Dar al-Salam',
    'year' => 2001,
]));
```

The slug is generated automatically from the configured source field. When owner mode is enabled, the new row inherits `$owner` and subsequent model queries are owner-scoped.

## Working with hierarchy

```php
$chapter = OwnerContext::withOwner($owner, fn (): Reference => Reference::create([
    'type' => ReferenceType::Book,
    'status' => ReferenceStatus::Published,
    'title' => 'Chapter 1',
    'parent_id' => (string) $reference->getKey(),
]));

$children = OwnerContext::withOwner($owner, fn () => $reference->children()->get());
```

Parents are validated on save: the parent must exist, must not be the row itself or one of its descendants, and must belong to the same owner (a global parent is accepted only when `include_global` is enabled).

## Working with parts

`Reference` uses `HasReferenceParts` directly:

```php
use AIArmada\References\Enums\ReferencePartType;
use AIArmada\References\Models\Reference;

$reference = Reference::query()->findOrFail($id);

$reference->setPart(ReferencePartType::Page, '142');
$reference->setPart(ReferencePartType::Chapter, '2');

$reference->hasPart(ReferencePartType::Page);
$reference->getPart('page');
$reference->getPartsGrouped();
```

Parts have one persisted representation: the `reference_parts` JSON attribute. Each entry has a `type` and `value`; `ReferencePartType` supplies the allowed vocabulary and labels.

## Deleting a reference subtree

`Reference::delete()` runs in a transaction: it iteratively collects the full `parent_id` subtree, then removes every row individually, children first, so each row fires its own `deleting`/`deleted` events and carries its media with it. Media deletes are chunked. The cascade covers the whole hierarchy regardless of row scope, and each row is removed inside its own owner context.

```php
use AIArmada\References\Models\Reference;

$reference = Reference::query()->findOrFail($id);
$reference->delete(); // children + covers/gallery removed with it
```

## Status lifecycle

Use `transitionStatus()` to move between statuses. Publishing stamps `published_at` when empty (direct saves of a `Published` row do the same); returning to draft clears it. Only one canonical reference (`is_canonical`) is allowed per owner.

```php
$reference->transitionStatus(ReferenceStatus::Published);
```

## Slug misconfiguration fails loud

`getSlugOptions()` reads `references.slug.source` and throws `InvalidArgumentException` when the source is missing, non-fillable, non-string-cast, or when `max_length` is not a positive integer. Fix the config value rather than catching the exception.

## Querying references

```php
use AIArmada\References\Enums\ReferenceType;
use AIArmada\References\Models\Reference;

$publishedBooks = OwnerContext::withOwner($owner, fn () => Reference::query()
    ->published()
    ->byType(ReferenceType::Book)
    ->get());
```

For intentional global work, enter an explicit global context with `OwnerContext::withOwner(null, ...)`. Do not remove the shared owner scope and add ad-hoc `owner_type` / `owner_id` filters.

## Media covers and gallery

`Reference` implements Spatie Media Library with three collections:

```php
use AIArmada\References\Models\Reference;

$reference = Reference::query()->findOrFail($id);

$reference
    ->addMedia($pathToFrontCover)
    ->toMediaCollection('front_cover');

$reference
    ->addMedia($pathToBackCover)
    ->toMediaCollection('back_cover');

$reference
    ->addMedia($pathToGalleryImage)
    ->toMediaCollection('gallery');

$frontUrl = $reference->getFirstMediaUrl('front_cover');
$gallery = $reference->getMedia('gallery');
```
