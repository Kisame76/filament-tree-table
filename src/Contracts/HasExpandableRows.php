<?php

declare(strict_types=1);

namespace Kisame76\FilamentTreeTable\Contracts;

use Kisame76\FilamentTreeTable\Concerns\InteractsWithExpandableRows;
use Kisame76\FilamentTreeTable\ExpandableRows;

/**
 * Implemented by the Livewire table component (a Filament List/ManageRelated page
 * or table widget) that should render its records as an expandable tree.
 *
 * Implement it by using the {@see InteractsWithExpandableRows}
 * trait, then apply {@see ExpandableRows} in the table() method.
 */
interface HasExpandableRows
{
    public function toggleRowExpansion(int|string $recordKey): void;

    public function isRowExpanded(int|string $recordKey): bool;

    /**
     * @return array<int, int|string>
     */
    public function getExpandedRowKeys(): array;

    public function expandAllRows(): void;

    public function collapseAllRows(): void;

    /**
     * Whether a search term or any table filter is currently active. While true the
     * table drops the tree restriction and shows a flat list of every match, so no
     * record stays hidden behind a collapsed parent.
     */
    public function isTreeTableFiltered(): bool;

    public function getRowDepth(int|string $recordKey): int;

    /**
     * @return array<string, int>
     */
    public function getRowDepthMap(): array;

    /**
     * @param  array<string, int>  $depthMap
     */
    public function setRowDepthMap(array $depthMap): void;

    /**
     * @param  array<int, int|string>  $parentKeys
     */
    public function setExpandableParentKeys(array $parentKeys): void;
}
