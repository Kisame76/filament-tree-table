<a href="https://github.com/Kisame76/filament-tree-table" class="filament-hidden">
    <img src="https://repository-images.githubusercontent.com/1272325875/8da5bceb-d314-41bd-9116-b9752a826776" alt="Filament Tree Table" style="width: 100%; max-width: 100%;" class="filament-hidden">
</a>

# Filament Tree Table

[![Filament](https://img.shields.io/badge/Filament-4.x%20%7C%205.x-FdAE4B?style=flat-square&logo=laravel&logoColor=white)](https://filamentphp.com)
[![Latest Version on Packagist](https://img.shields.io/packagist/v/kisame76/filament-tree-table.svg?style=flat-square)](https://packagist.org/packages/kisame76/filament-tree-table)
[![Tests](https://img.shields.io/github/actions/workflow/status/Kisame76/filament-tree-table/run-tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/Kisame76/filament-tree-table/actions/workflows/run-tests.yml)
[![Total Downloads](https://img.shields.io/packagist/dt/kisame76/filament-tree-table.svg?style=flat-square)](https://packagist.org/packages/kisame76/filament-tree-table)

Expandable parent/child **tree rows** for Filament v4 & v5 tables. Show only top-level
parents, expand them with a chevron, and render the sub-rows inline as real table
rows — search and filter stay correct, and you get expand-all / collapse-all buttons.

- ✅ Inline expandable rows that keep all your existing columns
- ✅ Search / filter aware — flatten to a flat match list, **or** keep the tree and reveal each match with its ancestor path (auto-expanded, non-matching ancestors dimmed as context)
- ✅ Custom sibling ordering — `defaultSort()` plus full `->sortable(query: ...)` / relationship-column support inside the tree
- ✅ Clear sub-rows without forcing an icon convention: a corner-arrow glyph on children and/or a coloured accent bar (+ optional per-depth tint) — mix or switch, fully themeable
- ✅ Expand-all / collapse-all header actions
- ✅ Database-agnostic ordering (Postgres / MySQL / SQLite)
- ✅ No stored `level`/depth column required — depth is derived from `parent_id`

## Requirements

- PHP 8.2+
- Filament v4 or v5

## Installation

```bash
composer require kisame76/filament-tree-table
```

The CSS auto-registers with Filament. After install (and on deploy) run:

```bash
php artisan filament:assets
```

Optionally publish the config:

```bash
php artisan vendor:publish --tag="filament-tree-table-config"
```

## Usage

Your model needs a self-referencing `parent_id` column and a `children()` HasMany
relationship (both names are configurable).

It takes two pieces — applying the tree where the table is defined, and opting the page in.

### 1. Apply the tree in your table definition

In Filament's resource structure the table lives in `Tables/<Name>Table::configure()`
(or directly in the resource's `table()` method). Wrap it with `ExpandableRows`:

```php
use Filament\Tables\Table;
use Kisame76\FilamentTreeTable\ExpandableRows;

class CategoriesTable
{
    public static function configure(Table $table): Table
    {
        return ExpandableRows::make()
            ->parentKey('parent_id')           // default
            ->childrenRelationship('children') // default
            ->applyTo(
                $table->columns([
                    // ... your normal columns
                ])
            );
    }
}
```

`applyTo()` prepends the chevron toggle column, wires the tree query, applies the
per-row styling, and adds the expand/collapse-all header actions.

### 2. Opt the page in

The **List page** is the Livewire component that holds the expand state, so add the
interface + trait there. It has no `table()` of its own — that stays in the table class:

```php
use Filament\Resources\Pages\ListRecords;
use Kisame76\FilamentTreeTable\Concerns\InteractsWithExpandableRows;
use Kisame76\FilamentTreeTable\Contracts\HasExpandableRows;

class ListCategories extends ListRecords implements HasExpandableRows
{
    use InteractsWithExpandableRows;

    protected static string $resource = CategoryResource::class;
}
```

> **Relation managers and table widgets** define `table()` on the component itself, so
> there is only one class: add the `ExpandableRows::make()->applyTo(...)` call **and** the
> `implements HasExpandableRows` + `use InteractsWithExpandableRows` to that same class.

## Configuration

Every cue is an independent toggle — combine or switch freely:

```php
ExpandableRows::make()
    ->parentKey('parent_id')                        // default
    ->childrenRelationship('children')              // default
    ->recordKey(fn ($record) => $record->getKey())  // for non-default primary keys
    ->grid(true)                                    // per-level stepping (indentation) on/off
    ->cornerArrow(true)                             // corner-down-right glyph on children
    ->accentBar(true)                               // coloured bar on the child's left edge
    ->depthTint(true)                               // per-depth background tint (child rows lighter)
    ->recordClasses(fn ($record, int $depth) => []) // extend/override row classes
    ->expandAllAction(true)
    ->collapseAllAction(true)
    ->flattenOnSort(false)                          // false (default): sort hierarchically, keep tree; true: flat sorted list
    ->flattenOnFilter(true)                         // true (default): a filter flattens the tree; false: keep the tree + reveal matches with ancestors
    ->flattenOnSearch(true)                         // true (default): a search flattens the tree; false: keep the tree + reveal matches with ancestors
    ->defaultSort('sort')                           // default sibling order: a column name...
    ->defaultSort(fn ($query, $direction) => $query->orderBy('sort', $direction)) // ...or a closure (order by a related/computed value)
    ->applyTo($table);
```

Project-wide defaults live in `config/filament-tree-table.php`.

### Theming

All visuals are driven by CSS variables — override them in your panel theme:

```css
.ftt-row {
  --ftt-slot: 1.5rem; /* width of each marker column (indent step) */
  --ftt-accent-color: rgb(99 102 241 / 0.85);
  --ftt-tint-color: rgb(99 102 241);
}
```

## How it works / caveats

- **Filtering / search:** by default (`flattenOnFilter(true)` / `flattenOnSearch(true)`)
  an active filter or search drops the tree and shows a flat list of every match, so
  nothing stays hidden behind a collapsed parent. Set either to `false` to keep the tree —
  matches are then shown together with their ancestor path (auto-expanded), and the
  non-matching ancestors are dimmed via the `.ftt-context` class (style it in your theme).
  While a filter/search drives the expansion the chevrons are non-interactive and the
  expand/collapse-all actions hide, so the displayed state can't be toggled out of sync.
- **Pagination** counts the visible tree rows; page sizes shift as you expand, and a
  branch can span a page boundary. For very deep trees consider `->paginated(false)`.
- **Sorting:** a column sort is delegated to the column itself, so `->sortable(query: ...)`
  closures and relationship/computed columns order the tree exactly as they would a flat
  table. Use `defaultSort()` for the sibling order when no column sort is active. With
  `flattenOnSort(false)` (default) the sort stays hierarchical — each sibling group follows
  the column while keeping the tree grouped under its parent (Jira-style); set
  `flattenOnSort(true)` to drop the tree and show a flat sorted list.
- **Stepping:** `grid(false)` removes the per-level indentation (flat rows); the hierarchy
  is then shown only by `accentBar`/`depthTint`. `grid` and `cornerArrow` are independent.
- **Column manager:** the chevron toggle column is kept out of the column-manager panel
  (the `toggleableColumns()` / `reorderableColumns()` dropdown) and is always pinned as the
  first column, so a persisted column order can never hide it or push it to the back. It
  still carries a non-breaking-space label internally (`->toggleColumnLabel('…')` to change
  it) because Filament rejects blank labels on reorderable columns — the label is no longer
  shown anywhere. The pinning is done by `InteractsWithExpandableRows`, which overrides
  Filament's `getDefaultTableColumnState()` / `updateTableColumns()`. On a List page,
  relation manager, or table widget this just works (the base class supplies
  `InteractsWithTable`). The only exception is a bare custom Livewire component that uses
  `InteractsWithTable` and `InteractsWithExpandableRows` side by side — there, resolve the
  trait conflict explicitly: `use InteractsWithTable, InteractsWithExpandableRows { InteractsWithExpandableRows::getDefaultTableColumnState insteadof InteractsWithTable; InteractsWithExpandableRows::updateTableColumns insteadof InteractsWithTable; }`.
- Components that do **not** implement `HasExpandableRows` (e.g. a widget sharing the
  same `table()` definition) render completely flat — every wired behaviour self-disables.

## License

MIT.
