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

## Working with parts

```php
use AIArmada\References\Enums\ReferencePartType;
use AIArmada\References\Traits\HasReferenceParts;
use Illuminate\Database\Eloquent\Model;

final class Citation extends Model
{
    use HasReferenceParts;
}

$citation->setPart(ReferencePartType::Page, '142');
$citation->setPart(ReferencePartType::Chapter, '2');

$citation->hasPart(ReferencePartType::Page);
$citation->getPart('page');
$citation->getPartsGrouped();
```

Parts have one persisted representation: the `reference_parts` JSON attribute. Each entry has a `type` and `value`; `ReferencePartType` supplies the allowed vocabulary and labels.

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
