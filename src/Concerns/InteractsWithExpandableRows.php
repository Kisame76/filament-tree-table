<?php

declare(strict_types=1);

namespace Kisame76\FilamentTreeTable\Concerns;

use Filament\Tables\Columns\Column;
use Filament\Tables\Concerns\HasColumnManager;
use Illuminate\Database\Eloquent\Builder;
use Kisame76\FilamentTreeTable\Contracts\HasExpandableRows;
use Kisame76\FilamentTreeTable\ExpandableRows;

/**
 * Drop-in implementation of {@see HasExpandableRows}
 * for any Filament table Livewire component (List/ManageRelated page or table widget).
 *
 * Holds the expanded-row state and exposes the toggle/expand-all/collapse-all hooks
 * the table chevron column and header actions call. Record keys are normalised to
 * strings so int and string (ULID/UUID) keys behave identically.
 *
 * Relies on the host being a Filament table component (provides getTableSearch(),
 * getTableColumnSearches() and getTable()).
 */
trait InteractsWithExpandableRows
{
    /**
     * Keys of parent rows whose children are currently expanded. Public so the state
     * survives between Livewire requests.
     *
     * @var array<int, string>
     */
    public array $expandedRows = [];

    /**
     * Every key that has at least one child, recomputed on each tree render. Public so
     * "expand all" still works on the request that follows a render.
     *
     * @var array<int, string>
     */
    public array $expandableParentKeys = [];

    /**
     * [key => depth] for the rows of the current render. Render-scoped (not persisted)
     * to keep the Livewire payload small; rebuilt every time the tree query is built.
     *
     * @var array<string, int>
     */
    protected array $treeRowDepthMap = [];

    /**
     * When true the table's own filter/search pass is skipped for the current query
     * build. Set by the tree while resolving an ancestor-inclusive filtered view, where
     * it has already applied the filters itself and pulled in matching rows' ancestors;
     * the outer pass must not strip those (non-matching) ancestors back out. Not
     * persisted — recomputed on every query build.
     */
    protected bool $treeSuppressFilterApplication = false;

    /**
     * Lookup of the keys expanded in the current render (user expansions plus any ancestors
     * the tree auto-expanded for filter matches). Render-scoped, keyed by string for O(1)
     * chevron-state lookups.
     *
     * @var array<string, true>
     */
    protected array $treeEffectiveExpandedKeys = [];

    /**
     * True while row expansion is driven by an active filter/search (auto-expanded). The
     * chevrons go non-interactive and the expand/collapse-all actions hide. Render-scoped.
     */
    protected bool $treeExpansionLocked = false;

    /**
     * Lookup of rows shown only as the path to a match (non-matching ancestors), dimmed as
     * context. Render-scoped, keyed by string.
     *
     * @var array<string, true>
     */
    protected array $treeContextKeys = [];

    public function toggleRowExpansion(int|string $recordKey): void
    {
        $recordKey = (string) $recordKey;

        $position = array_search($recordKey, $this->expandedRows, true);

        if ($position !== false) {
            unset($this->expandedRows[$position]);
            $this->expandedRows = array_values($this->expandedRows);

            return;
        }

        $this->expandedRows[] = $recordKey;
    }

    public function isRowExpanded(int|string $recordKey): bool
    {
        return in_array((string) $recordKey, $this->expandedRows, true);
    }

    /**
     * @return array<int, string>
     */
    public function getExpandedRowKeys(): array
    {
        return $this->expandedRows;
    }

    public function expandAllRows(): void
    {
        $this->expandedRows = array_values(array_map('strval', $this->expandableParentKeys));
    }

    public function collapseAllRows(): void
    {
        $this->expandedRows = [];
    }

    public function isTreeTableFiltered(): bool
    {
        if ($this->hasTableSearch() || filled($this->getTableSearch())) {
            return true;
        }

        foreach ($this->getTableColumnSearches() as $columnSearch) {
            if (filled($columnSearch)) {
                return true;
            }
        }

        return $this->getTable()->getFilterIndicators() !== [];
    }

    public function getRowDepth(int|string $recordKey): int
    {
        return $this->treeRowDepthMap[(string) $recordKey] ?? 0;
    }

    /**
     * @return array<string, int>
     */
    public function getRowDepthMap(): array
    {
        return $this->treeRowDepthMap;
    }

    /**
     * @param  array<string, int>  $depthMap
     */
    public function setRowDepthMap(array $depthMap): void
    {
        $this->treeRowDepthMap = $depthMap;
    }

    /**
     * @param  array<int, int|string>  $parentKeys
     */
    public function setExpandableParentKeys(array $parentKeys): void
    {
        $this->expandableParentKeys = array_values(array_map('strval', $parentKeys));
    }

    public function setSuppressTableFilters(bool $suppress): void
    {
        $this->treeSuppressFilterApplication = $suppress;
    }

    /**
     * @param  array<int, int|string>  $keys
     */
    public function setEffectiveExpandedKeys(array $keys): void
    {
        $this->treeEffectiveExpandedKeys = array_fill_keys(array_map('strval', $keys), true);
    }

    public function isRowEffectivelyExpanded(int|string $recordKey): bool
    {
        return isset($this->treeEffectiveExpandedKeys[(string) $recordKey]);
    }

    public function setExpansionLocked(bool $locked): void
    {
        $this->treeExpansionLocked = $locked;
    }

    public function isExpansionLocked(): bool
    {
        return $this->treeExpansionLocked;
    }

    /**
     * @param  array<int, int|string>  $keys
     */
    public function setContextKeys(array $keys): void
    {
        $this->treeContextKeys = array_fill_keys(array_map('strval', $keys), true);
    }

    public function isRowContext(int|string $recordKey): bool
    {
        return isset($this->treeContextKeys[(string) $recordKey]);
    }

    /**
     * Skip the table's filter pass while the tree resolves an ancestor-inclusive view
     * (it has already filtered and added ancestors). The variadic forwards any extra
     * arguments so the override stays compatible across filament/tables ^4.0 and ^5.0.
     */
    protected function applyFiltersToTableQuery(Builder $query, mixed ...$args): Builder
    {
        if ($this->treeSuppressFilterApplication) {
            return $query;
        }

        return parent::applyFiltersToTableQuery($query, ...$args);
    }

    protected function applySearchToTableQuery(Builder $query, mixed ...$args): Builder
    {
        if ($this->treeSuppressFilterApplication) {
            return $query;
        }

        return parent::applySearchToTableQuery($query, ...$args);
    }

    /**
     * Hide the tree toggle column from the column manager panel.
     *
     * Filament builds the manager (toggle + reorder list) from this state, mapping every
     * table column. The prepended chevron column has no real name, so it would surface as
     * a blank, draggable row. Dropping it here keeps it out of the panel entirely; it is
     * never user-toggleable (so {@see Column::isToggledHidden()} short-circuits to false)
     * and stays visible in the table regardless.
     *
     * Overrides {@see HasColumnManager::getDefaultTableColumnState()},
     * present across filament/tables ^4.0 and ^5.0.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getDefaultTableColumnState(): array
    {
        return array_values(array_filter(
            parent::getDefaultTableColumnState(),
            fn (array $item): bool => ($item['name'] ?? null) !== ExpandableRows::TOGGLE_COLUMN_NAME,
        ));
    }

    /**
     * Keep the tree toggle column pinned first after a column reorder.
     *
     * When reordering is active Filament rebuilds the table's columns from the persisted
     * order, which — because the toggle is excluded from {@see static::getDefaultTableColumnState()}
     * — drops it from the rendered set. Capture it beforehand and prepend it again so it
     * is always the first column, never pushed to the back by a persisted order.
     *
     * Overrides {@see HasColumnManager::updateTableColumns()},
     * present across filament/tables ^4.0 and ^5.0.
     */
    public function updateTableColumns(): void
    {
        $toggleColumn = $this->getTable()->getColumn(ExpandableRows::TOGGLE_COLUMN_NAME);

        parent::updateTableColumns();

        if ($toggleColumn === null) {
            return;
        }

        $layout = array_values(array_filter(
            $this->getTable()->getColumnsLayout(),
            fn (object $component): bool => ! ($component instanceof Column
                && $component->getName() === ExpandableRows::TOGGLE_COLUMN_NAME),
        ));

        $this->getTable()->columns([$toggleColumn, ...$layout]);
    }
}
