---
title: Usage
---

# Usage

## Creating a reference

```php
use AIArmada\References\Enums\ReferenceStatus;
use AIArmada\References\Enums\ReferenceType;
use AIArmada\References\Models\Reference;

$reference = Reference::create([
    'type' => ReferenceType::Book,
    'status' => ReferenceStatus::Published,
    'title' => 'Sahih al-Bukhari',
    'author' => 'Imam al-Bukhari',
    'publisher' => 'Dar al-Salam',
    'year' => 2001,
]);
```

The slug is generated automatically from the configured source field.

## Working with hierarchy

```php
$chapter = Reference::create([
    'type' => ReferenceType::Book,
    'status' => ReferenceStatus::Published,
    'title' => 'Chapter 1',
    'parent_id' => (string) $reference->getKey(),
]);

$children = $reference->children()->get();
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

## Querying references

```php
use AIArmada\References\Enums\ReferenceType;
use AIArmada\References\Models\Reference;

$publishedBooks = Reference::query()
    ->published()
    ->byType(ReferenceType::Book)
    ->get();
```
