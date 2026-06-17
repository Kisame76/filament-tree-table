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
- ✅ Search / filter aware — when filtering, the tree flattens so nothing hides behind a collapsed parent
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

- **Filtering / search:** while any filter or search is active the table shows a flat
  list of every match (no tree restriction) so children behind collapsed parents are
  still found; chevrons are hidden in that mode.
- **Pagination** counts the visible tree rows; page sizes shift as you expand. For very
  deep trees consider `->paginated(false)`.
- **Sorting:** with `flattenOnSort(false)` (default) a column sort is applied
  hierarchically — it orders each sibling group while keeping the tree grouped under its
  parent (Jira-style). Set `flattenOnSort(true)` to drop the tree and show a flat sorted
  list. (A relationship/computed sort column falls back to natural order.)
- **Stepping:** `grid(false)` removes the per-level indentation (flat rows); the hierarchy
  is then shown only by `accentBar`/`depthTint`. `grid` and `cornerArrow` are independent.
- **`reorderableColumns()`** rejects blank column labels, so the toggle column uses a
  non-breaking-space label by default (`->toggleColumnLabel('')` to change it).
- Components that do **not** implement `HasExpandableRows` (e.g. a widget sharing the
  same `table()` definition) render completely flat — every wired behaviour self-disables.

## License

MIT.
