<?php

declare(strict_types=1);

namespace Kisame76\FilamentTreeTable;

use Closure;
use Filament\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\HtmlString;
use Kisame76\FilamentTreeTable\Concerns\InteractsWithExpandableRows;
use Kisame76\FilamentTreeTable\Contracts\HasExpandableRows;
use Kisame76\FilamentTreeTable\Support\OrderByIds;
use Kisame76\FilamentTreeTable\Support\TreeBuilder;

/**
 * Fluent decorator that turns a regular Filament table into an expandable parent/child
 * tree. Apply it once in your table() method, after defining your own columns:
 *
 *     return ExpandableRows::make()
 *         ->parentKey('parent_id')
 *         ->childrenRelationship('children')
 *         ->applyTo($table->columns([...]));
 *
 * Sub-rows are marked two ways, mix or switch freely:
 *   - cornerArrow: a corner-down-right glyph on each child ("belongs to the row above")
 *   - accentBar:   a coloured left bar (+ optional per-depth tint)
 *
 * The host Livewire component must implement {@see HasExpandableRows} (use the
 * {@see InteractsWithExpandableRows} trait). When it does not — e.g. a widget sharing
 * the same table() definition — every wired behaviour self-disables and the table
 * renders flat.
 */
class ExpandableRows
{
    protected const CHEVRON_RIGHT = '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="m9 6 6 6-6 6"/></svg>';

    protected const CHEVRON_DOWN = '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="m6 9 6 6 6-6"/></svg>';

    protected const CORNER_ARROW = '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 5v8a3 3 0 0 0 3 3h9"/><path d="m15 12 4 4-4 4"/></svg>';

    protected string $parentKey;

    protected string $childrenRelationship;

    protected ?Closure $recordKeyResolver = null;

    protected bool $grid;

    protected bool $cornerArrow;

    protected bool $accentBar;

    protected bool $depthTint;

    protected ?Closure $recordClassesResolver = null;

    protected bool $expandAllAction;

    protected bool $collapseAllAction;

    protected bool $flattenOnSort;

    protected string $toggleColumnLabel = "\u{00A0}"; // nbsp: reorderableColumns() rejects blank labels.

    public function __construct()
    {
        $this->parentKey = (string) config('filament-tree-table.parent_key', 'parent_id');
        $this->childrenRelationship = (string) config('filament-tree-table.children_relationship', 'children');
        $this->grid = (bool) config('filament-tree-table.grid', true);
        $this->cornerArrow = (bool) config('filament-tree-table.corner_arrow', true);
        $this->accentBar = (bool) config('filament-tree-table.accent_bar', true);
        $this->depthTint = (bool) config('filament-tree-table.depth_tint', true);
        $this->expandAllAction = (bool) config('filament-tree-table.expand_all_action', true);
        $this->collapseAllAction = (bool) config('filament-tree-table.collapse_all_action', true);
        $this->flattenOnSort = (bool) config('filament-tree-table.flatten_on_sort', false);
    }

    public static function make(): static
    {
        return new static;
    }

    public function parentKey(string $parentKey): static
    {
        $this->parentKey = $parentKey;

        return $this;
    }

    public function childrenRelationship(string $relationship): static
    {
        $this->childrenRelationship = $relationship;

        return $this;
    }

    public function recordKey(Closure $resolver): static
    {
        $this->recordKeyResolver = $resolver;

        return $this;
    }

    /**
     * Toggle the per-level stepping (the indentation grid), independent of the corner
     * arrow. On = one slot per level; off = flat rows (hierarchy shown by tint/accent).
     */
    public function grid(bool $condition = true): static
    {
        $this->grid = $condition;

        return $this;
    }

    public function cornerArrow(bool $condition = true): static
    {
        $this->cornerArrow = $condition;

        return $this;
    }

    public function accentBar(bool $condition = true): static
    {
        $this->accentBar = $condition;

        return $this;
    }

    public function depthTint(bool $condition = true): static
    {
        $this->depthTint = $condition;

        return $this;
    }

    /**
     * Extend or override the per-row classes. Receives the record and its depth.
     *
     * @param  Closure(Model, int): array<int, string>  $resolver
     */
    public function recordClasses(Closure $resolver): static
    {
        $this->recordClassesResolver = $resolver;

        return $this;
    }

    public function expandAllAction(bool $condition = true): static
    {
        $this->expandAllAction = $condition;

        return $this;
    }

    public function collapseAllAction(bool $condition = true): static
    {
        $this->collapseAllAction = $condition;

        return $this;
    }

    public function flattenOnSort(bool $condition = true): static
    {
        $this->flattenOnSort = $condition;

        return $this;
    }

    public function toggleColumnLabel(string $label): static
    {
        $this->toggleColumnLabel = $label;

        return $this;
    }

    public function applyTo(Table $table): Table
    {
        $existingColumns = array_values($table->getColumns());

        $table
            ->modifyQueryUsing(fn (Builder $query, $livewire, bool $isResolvingRecord = false): Builder => $this->modifyQuery($query, $livewire, $isResolvingRecord))
            ->columns([$this->toggleColumn(), ...$existingColumns])
            ->recordClasses(fn (Model $record, $livewire = null): array => $this->recordClassesFor($record, $livewire));

        $headerActions = array_values(array_filter([
            $this->expandAllAction ? $this->buildExpandAllAction() : null,
            $this->collapseAllAction ? $this->buildCollapseAllAction() : null,
        ]));

        if ($headerActions !== []) {
            $table->pushHeaderActions($headerActions);
        }

        return $table;
    }

    public function modifyQuery(Builder $query, mixed $livewire, bool $isResolvingRecord = false): Builder
    {
        if ($isResolvingRecord || ! $livewire instanceof HasExpandableRows) {
            return $query;
        }

        if ($this->isFlat($livewire)) {
            $livewire->setRowDepthMap([]);

            return $query;
        }

        $model = $query->getModel();
        $qualifiedKey = $model->getQualifiedKeyName();
        $qualifiedParent = $model->qualifyColumn($this->parentKey);

        // When the tree is kept (flatten_on_sort = false) an active column sort is applied
        // to the lookup, so every sibling group follows that column while staying grouped
        // under its parent (Jira-style hierarchical sort). Otherwise rows fall back to key
        // order. Children come out in the same order as this fetch.
        $pairsQuery = (clone $query)->reorder();
        $this->applyTreeSort($pairsQuery, $model, $livewire);

        $pairs = $pairsQuery
            ->get([$qualifiedKey, $qualifiedParent])
            ->map(fn (Model $record): array => [$record->getKey(), $record->getAttribute($this->parentKey)]);

        $tree = TreeBuilder::build($pairs, $livewire->getExpandedRowKeys());

        $livewire->setRowDepthMap($tree['depth']);
        $livewire->setExpandableParentKeys($tree['parentsWithChildren']);

        if ($tree['ordered'] === []) {
            return $query->whereRaw('1 = 0');
        }

        $query
            ->withCount($this->childrenRelationship)
            ->whereKey($tree['ordered']);

        return OrderByIds::applyTo($query, $qualifiedKey, $tree['ordered']);
    }

    /**
     * Apply the active table sort to the tree lookup so every sibling group follows that
     * column (parents and their children alike), keeping the tree grouping. A relationship
     * or computed sort column (dotted name) is skipped — it cannot be ordered on the base
     * table — leaving the natural key order. A key tiebreaker keeps siblings deterministic.
     */
    protected function applyTreeSort(Builder $query, Model $model, HasExpandableRows $livewire): void
    {
        $sortColumn = method_exists($livewire, 'getTableSortColumn') ? $livewire->getTableSortColumn() : null;

        if (filled($sortColumn) && ! str_contains((string) $sortColumn, '.')) {
            $direction = $livewire->getTableSortDirection() === 'desc' ? 'desc' : 'asc';
            $query->orderBy($model->qualifyColumn($sortColumn), $direction);
        }

        $query->orderBy($model->getQualifiedKeyName());
    }

    protected function isFlat(HasExpandableRows $livewire): bool
    {
        if ($livewire->isTreeTableFiltered()) {
            return true;
        }

        return $this->flattenOnSort
            && method_exists($livewire, 'getTableSortColumn')
            && filled($livewire->getTableSortColumn());
    }

    protected function toggleColumn(): TextColumn
    {
        $countAttribute = $this->childrenRelationship.'_count';

        return TextColumn::make('__ftt_toggle')
            ->label($this->toggleColumnLabel)
            ->html()
            ->width('1px')
            ->grow(false)
            ->state(fn (Model $record, $livewire): HtmlString => new HtmlString(
                $this->markerHtml($record, $livewire, $countAttribute)
            ));
    }

    protected function markerHtml(Model $record, mixed $livewire, string $countAttribute): string
    {
        if (! $livewire instanceof HasExpandableRows) {
            return '';
        }

        $key = $this->keyOf($record);
        $depth = $livewire->getRowDepth($key);
        $hasChildren = (int) ($record->getAttribute($countAttribute) ?? 0) > 0;

        // grid = the per-level stepping, toggled independently of the corner arrow.
        //   grid on:  one fixed slot per level (depth + 1 slots). With the arrow the chevron
        //             sits in slot (depth-1) and the arrow in slot (depth), so a chevron
        //             lands exactly under the arrow of the level above; without the arrow the
        //             chevron itself steps (slot = depth).
        //   grid off: flat (no stepping) — just the chevron slot, plus an arrow slot for
        //             children when the arrow is on.
        if ($this->grid) {
            $totalSlots = $depth + 1;

            if ($this->cornerArrow) {
                $chevronColumn = $depth === 0 ? 0 : $depth - 1;
                $arrowColumn = $depth;
            } else {
                $chevronColumn = $depth;
                $arrowColumn = -1;
            }
        } elseif ($this->cornerArrow && $depth > 0) {
            $totalSlots = 2;
            $chevronColumn = 0;
            $arrowColumn = 1;
        } else {
            $totalSlots = 1;
            $chevronColumn = 0;
            $arrowColumn = -1;
        }

        $chevron = '';
        if ($hasChildren) {
            $svg = $livewire->isRowExpanded($key) ? static::CHEVRON_DOWN : static::CHEVRON_RIGHT;
            $arg = is_numeric($key) ? (string) $key : "'".addslashes((string) $key)."'";
            $chevron = '<button type="button" class="ftt-chevron" aria-label="Toggle row"'
                .' style="display:inline-flex;align-items:center;justify-content:center;padding:0;border:0;background:transparent;color:inherit;cursor:pointer"'
                .' wire:click.stop.prevent="toggleRowExpansion('.$arg.')">'.$svg.'</button>';
        }

        $arrow = ($this->cornerArrow && $depth > 0)
            ? '<span class="ftt-arrow" style="display:inline-flex;align-items:center;opacity:.45">'.static::CORNER_ARROW.'</span>'
            : '';

        $slots = '';
        for ($column = 0; $column < $totalSlots; $column++) {
            $content = match (true) {
                $column === $arrowColumn && $arrow !== '' => $arrow,
                $column === $chevronColumn => $chevron,
                default => '',
            };

            $slots .= '<span class="ftt-slot" style="flex:0 0 auto;display:inline-flex;align-items:center;justify-content:center;width:var(--ftt-slot, 1.25rem)">'.$content.'</span>';
        }

        return '<div class="ftt-toggle" style="display:inline-flex;align-items:center;white-space:nowrap;vertical-align:middle">'.$slots.'</div>';
    }

    /**
     * @return array<int, string>
     */
    protected function recordClassesFor(Model $record, mixed $livewire): array
    {
        $depth = 0;
        $classes = [];

        if ($livewire instanceof HasExpandableRows) {
            $depth = $livewire->getRowDepth($this->keyOf($record));
            $classes = ['ftt-row', 'ftt-depth-'.$depth, $depth > 0 ? 'ftt-child' : 'ftt-root'];

            if ($this->accentBar && $depth > 0) {
                $classes[] = 'ftt-accent';
            }

            if ($this->depthTint) {
                $classes[] = 'ftt-tint';
            }
        }

        if ($this->recordClassesResolver !== null) {
            $extra = ($this->recordClassesResolver)($record, $depth);
            $classes = [...$classes, ...array_values((array) $extra)];
        }

        return $classes;
    }

    protected function buildExpandAllAction(): Action
    {
        return Action::make('expandAllRows')
            ->label(__('Expand all'))
            ->icon('heroicon-m-chevron-double-down')
            ->color('gray')
            ->action(fn ($livewire) => $livewire instanceof HasExpandableRows ? $livewire->expandAllRows() : null)
            ->visible(fn ($livewire): bool => $livewire instanceof HasExpandableRows && ! $livewire->isTreeTableFiltered());
    }

    protected function buildCollapseAllAction(): Action
    {
        return Action::make('collapseAllRows')
            ->label(__('Collapse all'))
            ->icon('heroicon-m-chevron-double-up')
            ->color('gray')
            ->action(fn ($livewire) => $livewire instanceof HasExpandableRows ? $livewire->collapseAllRows() : null)
            ->visible(fn ($livewire): bool => $livewire instanceof HasExpandableRows && ! $livewire->isTreeTableFiltered());
    }

    protected function keyOf(Model $record): int|string
    {
        if ($this->recordKeyResolver !== null) {
            return ($this->recordKeyResolver)($record);
        }

        return $record->getKey();
    }
}
