---
title: Usage
---

# Usage

## Creating a reference

```php
use AIArmada\References\Enums\ReferenceStatus;
use AIArmada\References\Enums\ReferenceType;
use AIArmada\References\Enums\ReferenceRecordKind;
use AIArmada\References\Models\Reference;
use AIArmada\CommerceSupport\Contracts\OwnerResolverInterface;
use AIArmada\CommerceSupport\Support\OwnerContext;

$owner = app(OwnerResolverInterface::class)->resolve();

$reference = OwnerContext::withOwner($owner, fn (): Reference => Reference::create([
    'type' => ReferenceType::Book,
    'status' => ReferenceStatus::Published,
    'title' => 'Sahih al-Bukhari',
    'publisher' => 'Dar al-Salam',
    'year' => 2001,
]));
```

The slug is generated automatically from the configured source field. When owner mode is enabled, the new row inherits `$owner` and subsequent model queries are owner-scoped.
Slug uniqueness is owner-scoped; global references have their own uniqueness scope. Identity comparisons are byte-exact across supported databases, so case and trailing spaces are significant.

`save()`, `saveQuietly()`, and `saveOrIgnore()` all run the same field normalization, hierarchy, owner assignment, and publication checks. Quiet saves still receive owner assignment, slug generation, and validation; bulk query-builder writes bypass model guarantees and are unsupported.

## Working with hierarchy

```php
$chapter = OwnerContext::withOwner($owner, fn (): Reference => Reference::create([
    'type' => ReferenceType::Book,
    'status' => ReferenceStatus::Published,
    'title' => 'Chapter 1',
    'record_kind' => ReferenceRecordKind::Part,
    'parent_id' => (string) $reference->getKey(),
]));

$children = OwnerContext::withOwner($owner, fn () => $reference->children()->get());
```

Record kind defaults to `work`. An `edition` must belong to a work, while a `part` must belong to a work or edition. A work has no parent. Only editions accept `edition_number` (positive integer) and `edition_label` (maximum 255 characters). Kind changes cannot invalidate existing children.

Saves run inside a transaction and lock the reference tree root before hierarchy changes; unchanged hierarchy saves lock only the record. Status transitions use the same single lock path without refreshing the returned model. Stale hierarchy or publication state is rejected rather than silently overwritten. Parents are validated when hierarchy changes: the parent must exist, must not be the row itself or one of its descendants, and must belong to the same owner (a global parent is accepted only when `include_global` is enabled and include-global matching is not suppressed).

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

## Working with contributors

Authorship is a role on the polymorphic `reference_contributors` table, not a text column. Link any contributor model (persons, institutions, …) with an explicit role; links are an unordered set and read back stable by contributor ID:

```php
use AIArmada\References\Actions\SyncReferenceContributors;
use AIArmada\References\Enums\ReferenceContributorRole;

app(SyncReferenceContributors::class)->handle($reference, ReferenceContributorRole::Author, $author->getMorphClass(), [
    (string) $firstAuthor->getKey(),
    (string) $secondAuthor->getKey(),
]);

$authors = $reference->contributorsForRole(ReferenceContributorRole::Author)->with('contributor')->get();
```

Works own authorship: the `author` role can only be stored on works, and editions and parts inherit the effective contributors of their root work via `effectiveContributors()`. Other roles (for example an edition editor) may still be stored directly on any record kind. Contributor IDs must be UUIDs and are normalized to lowercase. Contributor types must resolve to a concrete Eloquent model and are stored using its canonical morph identity. An explicit role is required on every direct write, including `saveQuietly()`.

Contributor targets are validated before they are linked. Connection resolution matches an Eloquent morph relation: a target model with no explicit connection inherits the reference (or contributor link) connection, while a target model with an explicit connection is validated on that connection. A target model that uses `commerce-support`'s `HasOwner` must exist in the active owner scope; shared contributor models without `HasOwner` remain supported. `SyncReferenceContributors` performs one authorized reference lock, validates the target set in 500-ID batches, and replaces the role set with chunked bulk inserts and deletes. Batch size bounds each SQL parameter list; the action still requires the complete target set in memory. The sync path does not dispatch per-link model save/delete events; use individual model writes when link-level listener cancellation is required. Use the sync action for set mutation; individual model mutations remain guarded and require a transaction and matching write-owner context. Contributor queries inherit the owning reference's read scope, and a work with authorship cannot be changed to another record kind until its authors are removed. Sync enforces write-owner context, including explicit global context for global references. A unique index prevents duplicate contributor identities.

```php
$editionAuthors = $edition->effectiveContributors(ReferenceContributorRole::Author);
```

## Deleting a reference subtree

`Reference::delete()` delegates its model lifecycle to `DeleteReference`, which runs in a transaction. It locks the tree root, collects the complete `parent_id` subtree, fires every cancellation check before destructive work, and then deletes media rows, contributor links, and reference rows in 500-row batches. Root cancellation returns `false`; descendant cancellation rolls the transaction back. The caller model may be partially loaded and is hydrated from the locked row before events run.

Media rows must use the reference connection so they can join the same transaction. A media model without an explicit connection inherits the reference connection; a configured media model that explicitly uses a different connection is rejected before any cascade writes. Spatie's eager media-delete hook is suppressed for the cascade without permanently changing the caller's preserve-media flag.

Spatie's media files and `deleted` model events are deferred until after the transaction commits, so failures or cancellations leave the database and stored files intact. The original root and descendant owner contexts are captured before deletion and reused for deferred delivery; same-owner nodes reuse the active context, distinct owners are resolved once, and an orphaned owner row fails explicitly instead of being treated as global. A commit that runs under a different ambient owner therefore cannot reassign or globalize the deferred events. The cascade covers the whole hierarchy regardless of row scope, including a global root with owned descendants.

Only model deletion events are deferred; media files and event listeners are delivered best effort after the database commit. A failing deferred listener is reported, and later root or descendant notifications and media-file removal still run. Delivery ends at the commit boundary: out-of-band writes, inconsistent or missing stored owner tuples, and process termination before callbacks run are outside the cascade contract.

`deleteQuietly()` and `Reference::withoutEvents()` suppress both synchronous and deferred model deletion events while still deleting the reference rows and media rows. Calling Spatie's `deletePreservingMedia()` instead deletes the reference rows for the whole locked subtree but deliberately keeps the media rows and files for that subtree; no media connection check is performed because no media rows are touched.

```php
use AIArmada\References\Models\Reference;

$reference = Reference::query()->findOrFail($id);
$reference->delete(); // children + covers/gallery removed with it
```

## Status lifecycle

Use `TransitionReferenceStatus` to move between statuses. Publishing stamps `published_at` when empty; returning to draft clears it. Both rules also apply to direct model saves. The transition uses one transaction and one record lock, then returns the same model instance with updated state. Saves based on stale publication state are rejected; reload the reference before editing.

Bulk query-builder updates and raw SQL bypass reference validation, owner immutability, hierarchy guards, and publication rules; they are unsupported for reference writes.

```php
use AIArmada\References\Actions\TransitionReferenceStatus;

app(TransitionReferenceStatus::class)->handle($reference, ReferenceStatus::Published);
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

## ISBN and field validation

ISBN accepts ISBN-10 or ISBN-13 with a valid checksum. Spaces and hyphens are removed and the check digit is uppercased before persistence. `AIArmada\References\Rules\Isbn` can also be used in form validation. ISBN-13 must start with `978` or `979`.

Invalid reference fields or parent kinds throw Laravel `ValidationException` with the affected field key. URL accepts only HTTP/HTTPS and at most 255 characters; language accepts at most 10 characters.

## Breaking schema change

References now distinguish `work`, `edition`, and `part`; the owner-wide `is_canonical` flag is removed. The free-text `author` column is removed in favour of `reference_contributors` rows with the `author` role. The owning create-table migration is changed directly. Install against a freshly rebuilt schema and seed typed records with contributor links; no compatibility path or data backfill is supplied.
