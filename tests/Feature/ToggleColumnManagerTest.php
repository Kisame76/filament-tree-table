<?php

declare(strict_types=1);

use Kisame76\FilamentTreeTable\ExpandableRows;
use Kisame76\FilamentTreeTable\Tests\Fixtures\TreeTableHost;

/**
 * Boots the table host the way Livewire would (sets up the table + column manager)
 * without a full HTTP render, so the column-manager methods can be exercised directly.
 */
function bootHost(): TreeTableHost
{
    $host = new TreeTableHost;
    $host->bootedInteractsWithTable();

    return $host;
}

/**
 * @param  array<int, array<string, mixed>>  $state
 * @return array<int, string>
 */
function columnNames(array $state): array
{
    return array_values(array_map(fn (array $item): string => $item['name'], $state));
}

it('hides the tree toggle column from the column manager panel', function (): void {
    $names = columnNames(bootHost()->getDefaultTableColumnState());

    expect($names)->not->toContain(ExpandableRows::TOGGLE_COLUMN_NAME)
        ->and($names)->toEqual(['name', 'id']);
});

it('keeps the toggle column rendered even though it is absent from the manager state', function (): void {
    $host = bootHost();

    // Absent from the manager panel...
    expect(columnNames($host->getDefaultTableColumnState()))
        ->not->toContain(ExpandableRows::TOGGLE_COLUMN_NAME);

    // ...yet never user-toggleable, so it stays in the rendered (visible) set.
    expect($host->getTable()->getColumn(ExpandableRows::TOGGLE_COLUMN_NAME)->isToggleable())->toBeFalse()
        ->and(array_keys($host->getTable()->getVisibleColumns()))
        ->toContain(ExpandableRows::TOGGLE_COLUMN_NAME);
});

it('pins the toggle column first by default', function (): void {
    expect(array_key_first(bootHost()->getTable()->getColumns()))
        ->toBe(ExpandableRows::TOGGLE_COLUMN_NAME);
});

it('keeps the toggle column first after a column reorder', function (): void {
    $host = bootHost();

    // Simulate the user dragging the columns into reverse order and persisting it.
    $reversed = array_reverse($host->getDefaultTableColumnState());
    expect(columnNames($reversed))->toEqual(['id', 'name']); // toggle isn't even in the manager

    $host->applyTableColumnManager($reversed, wasReordered: true);

    $columns = array_keys($host->getTable()->getColumns());

    // Toggle pinned first; the user's reorder still honoured for the rest.
    expect($columns[0])->toBe(ExpandableRows::TOGGLE_COLUMN_NAME)
        ->and(array_slice($columns, 1))->toEqual(['id', 'name']);
});
