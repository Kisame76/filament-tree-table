# Changelog

All notable changes to `filament-tree-table` will be documented in this file.

## v1.3.1 - 2026-10-01

### Changed

- **Requires Filament 4.3 or newer on the v4 line** (`^4.3||^5.0`, was `^4.0||^5.0`). The column-manager tests exercise the toggle column against `Table::persistColumnsInSession()`, which Filament only added in 4.3.0, so the suite does not run on 4.0 to 4.2 and 4.3 is the oldest version actually tested. Filament 5 is unaffected, and Composer already installs the newest 4.x for anyone upgrading.

### Added

- `SECURITY.md` with a reporting address and the scope worth reviewing.
- Tests that mount a table through Livewire and draw it, so the toggle column, the chevrons, the expand-all and collapse-all actions and search are exercised through Filament's own rendering. The existing tests read the query only and never rendered a row.
- A release workflow: pushing a `v*` tag publishes the GitHub release, with the notes taken from the matching section of this file. A tag without a section fails instead of publishing an empty release.
- CI now runs the suite on PHP 8.2 to 8.5 against Laravel 11, 12 and 13 and Filament 4 and 5, plus one run on the lowest versions Composer allows. Workflow actions are pinned to commit SHAs and kept current by Dependabot.

## v1.3.0 - 2026-06-18

### Added

- **`paginateByRoot()`** — paginate by **root node** instead of by row, so a family (a root plus all of its currently visible descendants) is never split across a page boundary. Each page carries N roots (the per-page selection) plus all their visible descendants, so the row count per page varies; "Showing X to Y of Z" and the page count refer to roots. Off by default (`paginate_by_root` config key) to preserve the existing per-row pagination. No effect while the view is flattened (sort/filter/search), under a non-default pagination mode, or with `->paginated(false)`.
- **Translations** — the expand-all / collapse-all action labels are now translatable, with English (`en`) and German (`de`) included. Publish the language files with `php artisan vendor:publish --tag="filament-tree-table-translations"` to add or override locales.

### Note

- The `HasExpandableRows` contract gained `setOrderedKeys()` and `setPaginateByRoot()` (used by the tree internals). Implementations using the `InteractsWithExpandableRows` trait — the documented setup — get them automatically and need no changes.

## v1.2.0 - 2026-06-17

### Added

- **`defaultSort()`** — set the default order for sibling rows by column name or a `Closure(Builder $query, string $direction)`, e.g. order by a related/computed value instead of the primary key.
- **`flattenOnFilter()` / `flattenOnSearch()`** — choose whether an active filter or search drops the tree for a flat list. Both default to `true` (previous behaviour); set to `false` to keep the hierarchy.
- **Ancestor-inclusive filtering & search** — when the tree is kept while filtering/searching, a matching row is shown together with its ancestor path (auto-expanded), so a match buried in a collapsed branch still surfaces. Non-matching ancestors are dimmed as context (`.ftt-context`), the way a file-tree search greys the folder path.

### Fixed

- **Column sort now works inside the tree** — sibling ordering delegates to the column's own sort, so `->sortable(query: ...)` closures and relationship/computed columns are honoured instead of being silently ignored.
- **Filter-driven expansion is consistent** — while a filter/search auto-expands the tree, the per-row chevrons are non-interactive and the expand/collapse-all actions hide, so the chevron state can no longer disagree with what is rendered.
- **No more infinite recursion / out-of-memory** when searching with the tree kept (the ancestor lookup reached back into the table query).

### Note

- The `HasExpandableRows` contract gained a few methods (used by the tree internals). Implementations using the `InteractsWithExpandableRows` trait — the documented setup — get them automatically and need no changes.

## v1.1.0 - 2026-06-17

- **Tree toggle column pinned first & hidden from the column manager** — the chevron column no longer shows up in the `toggleableColumns()` / `reorderableColumns()` panel (where it appeared as a nameless, draggable row) and can no longer be reordered or pushed to the back by a persisted column order. It always renders as the first column.

## v1.0.0 - 2026-06-17

Initial release.

- Expandable parent/child **tree rows** for Filament v4 & v5 tables — sub-rows render inline as real table rows, keeping all your columns.
- **Search & filter aware:** while filtering the tree flattens to a list of every match, so nothing stays hidden behind a collapsed parent.
- **Configurable, icon-free clarity** — independent toggles: grid stepping, corner-arrow glyph on children, coloured accent bar, per-depth background tint. Fully themeable via CSS variables.
- **Expand-all / collapse-all** header actions.
- **Hierarchical (Jira-style) column sort** that keeps the tree grouped, or flatten-on-sort for a flat sorted list.
- **Database-agnostic ordering** (PostgreSQL / MySQL / SQLite).
- No stored `level`/depth column required — depth is derived from `parent_id`.
