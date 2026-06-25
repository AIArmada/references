---
title: References Context
package: aiarmada/references
status: current
surface: domain
family: knowledge-and-references
---

# References Context

## Snapshot

- Composer: `aiarmada/references`
- Role: Generic reference/source management with slugs, hierarchy, and structured citation parts
- Search first: `src/Models`, `src/Actions`, `src/Traits`, `src/Enums`, `config`, `docs`
- Related: `commerce-support`, `events`

## Read next

1. `docs/01-overview.md`
2. `docs/03-configuration.md`
3. `docs/04-usage.md`
4. `docs/99-troubleshooting.md`
5. `docs/02-installation.md` when installation or publishing changes are involved

## Guardrails

- Owns reference records, slug generation, self-referencing hierarchy, and part helpers.
- Keeps citation and reference data out of unrelated domain packages unless they deliberately depend on it.
- Uses UUID primary keys and configurable JSON column types, with no DB foreign keys or cascades.
- Does not impose tenant ownership by default.
- Updates `docs/*.md` in the same pass when public behavior or config changes.
