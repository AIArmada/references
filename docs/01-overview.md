---
title: Overview
---

# Overview

`aiarmada/references` stores reference and source records for Laravel applications. It is built for bibliographic-style data with self-referencing parents, structured parts, and media covers.

## What this package owns

- Reference records with a type, status, title, slug, and optional metadata
- Typed work, edition, and part records with validated parent relationships
- Polymorphic contributor links (`reference_contributors`) with explicit roles such as author, editor, and translator
- Slug generation rules
- Structured `reference_parts` JSON data for book sections, pages, chapters, or surah/juz/jilid references
- Enum-backed type and status values
- Media collections for covers and gallery images via Spatie Media Library

## What this package does not own

- The source material itself
- Admin UI
- Owner resolution itself; it consumes owner context from `commerce-support`
- Database foreign keys, cascades, or soft deletes

## Core concepts

- **Reference** - the canonical record for a book, article, thesis, website, or similar source
- **Slug** - the URL-friendly identifier generated from the configured source field
- **Work** - the identity of a source independent of its publication edition
- **Edition** - a publication of a work, carrying edition number/label, publisher, year, ISBN and language
- **Parent** - a work containing editions or parts, or an edition containing parts
- **Part** - a structured subcomponent stored in `reference_parts`
- **Contributor** - a polymorphic link from a reference to an authoring party (person, institution, …) with a role; works own authorship and editions/parts inherit it
- **Media** - cover and gallery images registered on the `Reference` model

## Key features

- UUID primary keys
- Automatic slug generation with collision handling
- Draft, published, and archived lifecycle states
- Work → edition → part hierarchy through `parent_id`; direct work → part links support unknown editions
- Structured part helpers for content like chapter, section, page, or surah references
- Media collections: `front_cover`, `back_cover` (single file), and `gallery` (multiple)

## Models, traits, and actions

| Surface | Purpose |
| --- | --- |
| `Models\Reference` | Stores reference data, hierarchy, slugs (via Spatie `HasSlug`), and media collections |
| `Models\ReferenceContributor` | Stores one polymorphic contributor link (`reference_id`, `contributor_type`/`contributor_id`, `role`) |
| `Enums\ReferenceContributorRole` | Contributor vocabulary: `author`, `editor`, `translator` |
| `Traits\HasReferenceParts` | Adds helpers for reading and mutating structured parts |

Slugs are generated on the model itself with `spatie/laravel-sluggable` (`HasSlug` + `SlugOptions`); there is no `Actions\GenerateReferenceSlugAction` class (there is no `src/Actions/` directory).

## Requirements

- PHP 8.5+
- Laravel 13+
- `spatie/laravel-sluggable` ^4
- `spatie/laravel-medialibrary` ^11

## Owner scoping

`Reference` uses `commerce-support`'s `HasOwner` and `HasOwnerScopeConfig` traits. With `references.owner.enabled` enabled (the default), reads use the shared owner scope and new references inherit the current owner inside `OwnerContext`. Use explicit global context for intentional global records; a missing owner is not an all-owner query.

Slugs are unique per owner (plus a separate global namespace) via partial unique indexes, while reads stay owner-scoped. The generator still appends a numeric suffix when a slug already exists anywhere; pick a distinct slug when a per-owner conflict is reported.
