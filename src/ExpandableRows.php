<?php

declare(strict_types=1);

namespace Kisame76\FilamentTreeTable;

use Closure;
use Filament\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
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
    /**
     * Name of the prepended chevron/marker column. The host's
     * {@see InteractsWithExpandableRows} trait keys off this to hide the column from the
     * column manager panel and keep it pinned first.
     */
    public const TOGGLE_COLUMN_NAME = '__ftt_toggle';

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

    protected bool $flattenOnFilter;

    protected bool $flattenOnSearch;

    protected bool $paginateByRoot;

    protected string|Closure|null $defaultSort = null;

    protected string $defaultSortDirection = 'asc';

    /**
     * Guards against re-entering the tree query build. Resolving the ancestor-inclusive
     * match set calls the host's filter pass, which can reach back into Table::getQuery()
     * (e.g. via getModel()); without this the scope would recurse into itself.
     */
    protected bool $buildingTree = false;

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
        $this->flattenOnFilter = (bool) config('filament-tree-table.flatten_on_filter', true);
        $this->flattenOnSearch = (bool) config('filament-tree-table.flatten_on_search', true);
        $this->paginateByRoot = (bool) config('filament-tree-table.paginate_by_root', false);
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

    /**
     * Whether an active table filter drops the tree for a flat list. Off keeps the
     * hierarchy while filtered — useful to permanently hide a subset (e.g. completed
     * rows) without losing the tree. Filtered-out parents still drop their subtree.
     */
    public function flattenOnFilter(bool $condition = true): static
    {
        $this->flattenOnFilter = $condition;

        return $this;
    }

    /**
     * Whether an active table search drops the tree for a flat list. Usually wanted —
     * a search spans every level, so a flat result reads better than a sparse tree.
     */
    public function flattenOnSearch(bool $condition = true): static
    {
        $this->flattenOnSearch = $condition;

        return $this;
    }

    /**
     * Paginate by root node instead of by row, so a family (a root plus all of its
     * currently visible descendants) is never split across a page boundary. Each page
     * carries N roots — N being the per-page selection — and all their visible
     * descendants, so the row count per page varies; the "Showing X to Y of Z" summary
     * and the page count refer to roots. Off by default (rows are paginated as usual).
     * No effect while the view is flattened (sort/filter/search) or pagination is off.
     */
    public function paginateByRoot(bool $condition = true): static
    {
        $this->paginateByRoot = $condition;

        return $this;
    }

    /**
     * Default ordering for sibling rows when no column sort is active. Accepts a column
     * name (ordered on the base table) or a Closure(Builder $query, string $direction)
     * for full control — e.g. ordering by a related or computed value. Only the order
     * within each sibling group changes; the hierarchy is preserved.
     */
    public function defaultSort(string|Closure|null $column, string $direction = 'asc'): static
    {
        $this->defaultSort = $column;
        $this->defaultSortDirection = $direction;

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

        // Re-entered while already building the tree (the ancestor match pass reaches back
        // into Table::getQuery() via the host's filter form). Hand back the plain query so
        // the inner call resolves without recursing.
        if ($this->buildingTree) {
            return $query;
        }

        $this->buildingTree = true;

        try {
            // Reset on every build; the ancestor-inclusive branch re-enables it as needed.
            $livewire->setSuppressTableFilters(false);

            if ($this->isFlat($livewire)) {
                $livewire->setRowDepthMap([]);
                $livewire->setEffectiveExpandedKeys($livewire->getExpandedRowKeys());
                $livewire->setExpansionLocked(false);
                $livewire->setContextKeys([]);
                // A flat view has no roots to paginate by; fall back to row pagination.
                $livewire->setOrderedKeys([]);
                $livewire->setPaginateByRoot(false);

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

            $expandedKeys = $livewire->getExpandedRowKeys();

            // Ancestor-inclusive filtering: when the tree is kept while a filter/search is
            // active, narrow to the matching rows, pull in their ancestors for context and
            // auto-expand those ancestors so matches buried in collapsed branches surface.
            // The tree applies the filters itself here, then suppresses the table's own pass
            // so the (non-matching) ancestor rows are not stripped back out downstream.
            $autoExpanded = $this->hasActiveSearch($livewire) || $this->hasActiveFilters($livewire);
            $contextKeys = [];

            if ($autoExpanded) {
                [$pairs, $expandedKeys, $contextKeys] = $this->revealMatchesWithAncestors($query, $pairs, $expandedKeys, $livewire, $qualifiedKey);
                $livewire->setSuppressTableFilters(true);
            }

            $tree = TreeBuilder::build($pairs, $expandedKeys);

            $livewire->setRowDepthMap($tree['depth']);
            $livewire->setExpandableParentKeys($tree['parentsWithChildren']);

            // Reflect the keys actually expanded this render (incl. auto-expanded ancestors)
            // so the chevrons match, lock manual expansion while a filter/search drives it,
            // and dim the non-matching ancestors shown only as path context.
            $livewire->setEffectiveExpandedKeys($expandedKeys);
            $livewire->setExpansionLocked($autoExpanded);
            $livewire->setContextKeys($contextKeys);

            // Hand the depth-first key order to the host so it can paginate by root block
            // (roots + their visible descendants) instead of by row when opted in.
            $livewire->setOrderedKeys($tree['ordered']);
            $livewire->setPaginateByRoot($this->paginateByRoot);

            if ($tree['ordered'] === []) {
                return $query->whereRaw('1 = 0');
            }

            $query
                ->withCount($this->childrenRelationship)
                ->whereKey($tree['ordered']);

            return OrderByIds::applyTo($query, $qualifiedKey, $tree['ordered']);
        } finally {
            $this->buildingTree = false;
        }
    }

    /**
     * Order the tree lookup so every sibling group follows the chosen order (parents and
     * their children alike), keeping the tree grouping. An active column sort is delegated
     * to the column itself, so ->sortable(query:) closures and relationship columns work
     * exactly as on a flat table; otherwise the configured defaultSort() applies. A key
     * tiebreaker keeps siblings deterministic.
     */
    protected function applyTreeSort(Builder $query, Model $model, HasExpandableRows $livewire): void
    {
        if (! $this->applyActiveColumnSort($query, $livewire)) {
            $this->applyDefaultSort($query, $model);
        }

        $query->orderBy($model->getQualifiedKeyName());
    }

    /**
     * Delegate sibling ordering to the active table column sort. Routing through the
     * column's own applySort() honours ->sortable(query:) closures and relationship
     * (dotted) columns — the same behaviour a flat Filament table gives. Returns false
     * when there is no active, sortable, visible column to apply.
     */
    protected function applyActiveColumnSort(Builder $query, HasExpandableRows $livewire): bool
    {
        if (! method_exists($livewire, 'getTableSortColumn') || ! method_exists($livewire, 'getTable')) {
            return false;
        }

        $sortColumn = $livewire->getTableSortColumn();

        if (blank($sortColumn)) {
            return false;
        }

        $column = $livewire->getTable()->getSortableVisibleColumn((string) $sortColumn);

        if ($column === null) {
            return false;
        }

        $direction = (method_exists($livewire, 'getTableSortDirection') && $livewire->getTableSortDirection() === 'desc')
            ? 'desc'
            : 'asc';

        $column->applySort($query, $direction);

        return true;
    }

    protected function applyDefaultSort(Builder $query, Model $model): void
    {
        if ($this->defaultSort instanceof Closure) {
            ($this->defaultSort)($query, $this->defaultSortDirection);

            return;
        }

        if (is_string($this->defaultSort)) {
            $query->orderBy($model->qualifyColumn($this->defaultSort), $this->defaultSortDirection);
        }
    }

    /**
     * Narrow the pair list to the rows matching the active filter/search plus all of
     * their ancestors, and expand those ancestors so the matches become visible. Matches
     * are resolved by the table's own filter/search; the ancestor chain is then walked
     * from the in-memory pair list (no extra per-row queries).
     *
     * @param  Collection<int, array{0: int|string, 1: int|string|null}>  $pairs
     * @param  array<int, int|string>  $expandedKeys
     * @return array{0: Collection<int, array{0: int|string, 1: int|string|null}>, 1: array<int, string>, 2: array<int, string>}
     */
    protected function revealMatchesWithAncestors(Builder $query, $pairs, array $expandedKeys, HasExpandableRows $livewire, string $qualifiedKey): array
    {
        $matchedKeys = $this->matchedKeys($query, $livewire, $qualifiedKey);

        if ($matchedKeys === []) {
            return [$pairs->take(0), $expandedKeys, []];
        }

        $parentOf = [];

        foreach ($pairs as [$key, $parent]) {
            $parentOf[(string) $key] = ($parent === null || $parent === '') ? null : (string) $parent;
        }

        $visible = array_fill_keys($matchedKeys, true);
        $ancestors = [];

        foreach ($matchedKeys as $matchedKey) {
            $cursor = $parentOf[$matchedKey] ?? null;

            while ($cursor !== null) {
                $visible[$cursor] = true;
                $alreadyWalked = isset($ancestors[$cursor]);
                $ancestors[$cursor] = true;

                if ($alreadyWalked) {
                    break; // this chain is already walked up to its root
                }

                $cursor = $parentOf[$cursor] ?? null;
            }
        }

        $pairs = $pairs->filter(fn (array $pair): bool => isset($visible[(string) $pair[0]]))->values();

        $expandedKeys = array_values(array_unique([
            ...array_map('strval', $expandedKeys),
            ...array_keys($ancestors),
        ]));

        // Ancestors that are not themselves matches are shown only as path context.
        $matched = array_fill_keys($matchedKeys, true);
        $contextKeys = array_values(array_filter(
            array_keys($ancestors),
            fn (string $key): bool => ! isset($matched[$key]),
        ));

        return [$pairs, $expandedKeys, $contextKeys];
    }

    /**
     * Primary keys of the rows matching the active filter/search. Suppression is lifted
     * for this lookup so the table applies its filters/search normally.
     *
     * @return array<int, string>
     */
    protected function matchedKeys(Builder $query, HasExpandableRows $livewire, string $qualifiedKey): array
    {
        $livewire->setSuppressTableFilters(false);

        $matchQuery = (clone $query)->reorder();

        if (method_exists($livewire, 'filterTableQuery')) {
            $livewire->filterTableQuery($matchQuery);
        }

        return array_map('strval', $matchQuery->get([$qualifiedKey])->modelKeys());
    }

    /**
     * A plain tree is shown: not flattened and not driven by an active filter/search. Only
     * then do manual expansion controls (per-row chevrons, expand/collapse-all) apply.
     */
    protected function isPlainTree(HasExpandableRows $livewire): bool
    {
        return ! $this->isFlat($livewire)
            && ! $this->hasActiveSearch($livewire)
            && ! $this->hasActiveFilters($livewire);
    }

    protected function isFlat(HasExpandableRows $livewire): bool
    {
        if ($this->flattenOnSearch && $this->hasActiveSearch($livewire)) {
            return true;
        }

        if ($this->flattenOnFilter && $this->hasActiveFilters($livewire)) {
            return true;
        }

        return $this->flattenOnSort
            && method_exists($livewire, 'getTableSortColumn')
            && filled($livewire->getTableSortColumn());
    }

    protected function hasActiveSearch(HasExpandableRows $livewire): bool
    {
        if (method_exists($livewire, 'getTableSearch') && filled($livewire->getTableSearch())) {
            return true;
        }

        if (method_exists($livewire, 'getTableColumnSearches')) {
            foreach ($livewire->getTableColumnSearches() as $columnSearch) {
                if (filled($columnSearch)) {
                    return true;
                }
            }
        }

        return false;
    }

    protected function hasActiveFilters(HasExpandableRows $livewire): bool
    {
        return method_exists($livewire, 'getTable')
            && $livewire->getTable()->getFilterIndicators() !== [];
    }

    protected function toggleColumn(): TextColumn
    {
        $countAttribute = $this->childrenRelationship.'_count';

        return TextColumn::make(self::TOGGLE_COLUMN_NAME)
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
            $svg = $livewire->isRowEffectivelyExpanded($key) ? static::CHEVRON_DOWN : static::CHEVRON_RIGHT;

            if ($livewire->isExpansionLocked()) {
                // An active filter/search drives expansion: show the state, but make it
                // non-interactive so a manual toggle cannot disagree with what is rendered.
                $chevron = '<span class="ftt-chevron" aria-hidden="true"'
                    .' style="display:inline-flex;align-items:center;justify-content:center;opacity:.55">'.$svg.'</span>';
            } else {
                $arg = is_numeric($key) ? (string) $key : "'".addslashes((string) $key)."'";
                $chevron = '<button type="button" class="ftt-chevron" aria-label="Toggle row"'
                    .' style="display:inline-flex;align-items:center;justify-content:center;padding:0;border:0;background:transparent;color:inherit;cursor:pointer"'
                    .' wire:click.stop.prevent="toggleRowExpansion('.$arg.')">'.$svg.'</button>';
            }
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
            $key = $this->keyOf($record);
            $depth = $livewire->getRowDepth($key);
            $classes = ['ftt-row', 'ftt-depth-'.$depth, $depth > 0 ? 'ftt-child' : 'ftt-root'];

            if ($this->accentBar && $depth > 0) {
                $classes[] = 'ftt-accent';
            }

            if ($this->depthTint) {
                $classes[] = 'ftt-tint';
            }

            // Non-matching ancestor shown only as the path to a match — dim it as context.
            if ($livewire->isRowContext($key)) {
                $classes[] = 'ftt-context';
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
            ->label(__('filament-tree-table::tree-table.actions.expand_all'))
            ->icon('heroicon-m-chevron-double-down')
            ->color('gray')
            ->action(fn ($livewire) => $livewire instanceof HasExpandableRows ? $livewire->expandAllRows() : null)
            ->visible(fn ($livewire): bool => $livewire instanceof HasExpandableRows && $this->isPlainTree($livewire));
    }

    protected function buildCollapseAllAction(): Action
    {
        return Action::make('collapseAllRows')
            ->label(__('filament-tree-table::tree-table.actions.collapse_all'))
            ->icon('heroicon-m-chevron-double-up')
            ->color('gray')
            ->action(fn ($livewire) => $livewire instanceof HasExpandableRows ? $livewire->collapseAllRows() : null)
            ->visible(fn ($livewire): bool => $livewire instanceof HasExpandableRows && $this->isPlainTree($livewire));
    }

    protected function keyOf(Model $record): int|string
    {
        if ($this->recordKeyResolver !== null) {
            return ($this->recordKeyResolver)($record);
        }

        return $record->getKey();
    }
}
