# Changelog

All notable changes to `filament-tree-table` will be documented in this file.

## v1.0.0 - 2026-06-17

Initial release.

- Expandable parent/child **tree rows** for Filament v4 & v5 tables — sub-rows render inline as real table rows, keeping all your columns.
- **Search & filter aware:** while filtering the tree flattens to a list of every match, so nothing stays hidden behind a collapsed parent.
- **Configurable, icon-free clarity** — independent toggles: grid stepping, corner-arrow glyph on children, coloured accent bar, per-depth background tint. Fully themeable via CSS variables.
- **Expand-all / collapse-all** header actions.
- **Hierarchical (Jira-style) column sort** that keeps the tree grouped, or flatten-on-sort for a flat sorted list.
- **Database-agnostic ordering** (PostgreSQL / MySQL / SQLite).
- No stored `level`/depth column required — depth is derived from `parent_id`.
