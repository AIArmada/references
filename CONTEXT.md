---
title: References Context
package: references
status: current
surface: domain
family: knowledge-and-references
keywords:
  - reference
  - citation
  - bibliography
  - slug
---

# References Context

## Snapshot
- Composer: `aiarmada/references`
- Role: Bibliographic references: sources, slugs, parent/child hierarchy, structured parts, media covers.
- Triggers: reference, citation, bibliography, slug
- Search first: `src/Models, src/Traits, config, docs`
- Related: `events`, `commerce-support`

## Read next
1. `docs/01-overview.md`
2. `docs/03-configuration.md`
3. `docs/04-usage.md`
4. `docs/99-troubleshooting.md`
5. related package contexts when the change crosses boundaries
6. `docs/02-installation.md` when setup or publishing changes are involved

## Guardrails
- Owns models, actions, services, events, calculations, and persistence rules.
- Update `docs/*.md` in the same pass when public behavior or config changes.

## Decide fast
- Use when: Citations or reference hierarchies.
- Skip when: No owner scope, no admin UI ships.
- Owner/security: No owner scope by design.

## Key surfaces
- Models: `Reference`
- Config `references.php`: `database`, `table_prefix`, `json_column_type`, `tables`, `references`, `slug`, `source`, `max_length`, `media`, `disk`

## Docs map
- Start: `01-overview` → `03-configuration` → `04-usage` → `99-troubleshooting`
- Deep dives: none — the five canonical docs cover this package
