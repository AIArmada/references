---
name: references-development
description: Manage bibliographic references, typed hierarchies, contributors, and citation parts.
---

# References development

- Use AIArmada\References\Models\Reference for owner-scoped reads and persistence guards. Quiet saves and saveOrIgnore still validate fields, hierarchy, publication, and owner assignment.
- Resolve AIArmada\References\Actions\SyncReferenceContributors and TransitionReferenceStatus through the container and call handle().
- Contributor roles are explicit; owner-scoped targets must be visible in the active owner scope. Shared target models without commerce-support HasOwner remain supported.
- Only works own authors; editions and parts inherit authors from the work.
- Never disable owner scope for consumer writes. Use explicit global owner context for global changes.
- Call the model's delete() for tree-locked subtree cleanup. Cancellation runs before destructive work; media files and deleted events are delivered after commit.
- Bulk query-builder or raw SQL reference writes bypass hierarchy, owner, and publication guarantees and are unsupported.
- Verify changed behavior with tests/src/References and package architecture checks.
