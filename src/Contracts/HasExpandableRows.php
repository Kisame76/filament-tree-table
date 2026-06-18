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

    /**
     * Suppress the table's own filter/search pass for the current query build. The tree
     * uses this while resolving an ancestor-inclusive filtered view: it applies the
     * filters itself to find matches, pulls in their ancestors for context, then stops
     * the outer pass from stripping those (non-matching) ancestor rows back out.
     */
    public function setSuppressTableFilters(bool $suppress): void;

    /**
     * The keys actually expanded in the current render. Equals the user's expanded rows in
     * a plain tree, but also includes the ancestors the tree auto-expands to surface filter
     * matches — so the chevron glyph reflects what is really shown.
     *
     * @param  array<int, int|string>  $keys
     */
    public function setEffectiveExpandedKeys(array $keys): void;

    public function isRowEffectivelyExpanded(int|string $recordKey): bool;

    /**
     * Whether row expansion is currently driven by the tree (an active filter/search
     * auto-expands ancestors). While locked the per-row chevrons are non-interactive and
     * the expand/collapse-all actions are hidden, since manual toggling cannot win.
     */
    public function setExpansionLocked(bool $locked): void;

    public function isExpansionLocked(): bool;

    /**
     * Keys of rows shown only as the path to a filter/search match (non-matching ancestors).
     * They are dimmed as context so it is clear they are not results themselves.
     *
     * @param  array<int, int|string>  $keys
     */
    public function setContextKeys(array $keys): void;

    public function isRowContext(int|string $recordKey): bool;

    /**
     * The depth-first ordered list of visible row keys for the current render. The tree
     * uses it to slice pagination by root (each root and its visible descendants form one
     * block), so families are not split across page boundaries.
     *
     * @param  array<int, int|string>  $keys
     */
    public function setOrderedKeys(array $keys): void;

    /**
     * Whether the current build should paginate by root node rather than by row. Mirrors
     * the {@see ExpandableRows::paginateByRoot()} option for the build in progress.
     */
    public function setPaginateByRoot(bool $paginateByRoot): void;
}
