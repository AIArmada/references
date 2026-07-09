---
title: Overview
---

# Overview

`aiarmada/references` stores reference and source records for Laravel applications. It is built for bibliographic-style data with self-referencing parents, structured parts, and media covers.

## What this package owns

- Reference records with a type, status, title, slug, and optional metadata
- Self-referencing parent and child relationships
- Slug generation rules
- Structured reference parts such as book sections, pages, chapters, or surah/juz/jilid references
- Enum-backed type and status values
- Media collections for covers and gallery images via Spatie Media Library

## What this package does not own

- The source material itself
- Admin UI
- Tenant ownership or owner-boundary rules
- Database foreign keys, cascades, or soft deletes

## Core concepts

- **Reference** - the canonical record for a book, article, thesis, website, or similar source
- **Slug** - the URL-friendly identifier generated from the configured source field
- **Parent** - a reference that acts as the container for a more specific reference
- **Part** - a structured subcomponent stored in `reference_parts`
- **Media** - cover and gallery images registered on the `Reference` model

## Key features

- UUID primary keys
- Automatic slug generation with collision handling
- Draft, published, and archived lifecycle states
- Self-referencing tree support through `parent_id`
- Structured part helpers for content like chapter, section, page, or surah references
- Media collections: `front_cover`, `back_cover` (single file), and `gallery` (multiple)

## Models, traits, and actions

| Surface | Purpose |
| --- | --- |
| `Models\Reference` | Stores reference data, hierarchy, and media collections |
| `Traits\HasReferenceParts` | Adds helpers for reading and mutating structured parts |
| `Actions\GenerateReferenceSlugAction` | Resolves a unique slug from a configurable source field |

## Requirements

- PHP 8.4+
- Laravel 13+
- `spatie/laravel-sluggable` ^4
- `spatie/laravel-medialibrary` ^11
