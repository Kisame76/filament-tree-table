<?php

declare(strict_types=1);

namespace Kisame76\FilamentTreeTable\Concerns;

use Kisame76\FilamentTreeTable\Contracts\HasExpandableRows;

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
}
