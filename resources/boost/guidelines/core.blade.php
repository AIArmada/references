# References

References is a focused Dune package. Use owner-scoped Reference queries and the concrete actions for contributor sync and status transitions. A work owns authorship; editions and parts inherit it. Use explicit global owner context for global writes. Reference saves validate hierarchy, publication, owner, and fields even through quiet saves; saveOrIgnore keeps the same path. Deleting a reference locks the tree root, validates cancellations before writes, and defers media file removal plus deleted events until commit. Do not bulk-delete or bulk-update reference rows.
