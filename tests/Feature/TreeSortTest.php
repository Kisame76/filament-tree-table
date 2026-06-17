<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Kisame76\FilamentTreeTable\Tests\Fixtures\Node;
use Kisame76\FilamentTreeTable\Tests\Fixtures\SortableTreeTableHost;

uses(RefreshDatabase::class);

function bootSortableHost(): SortableTreeTableHost
{
    $host = new SortableTreeTableHost;
    $host->bootedInteractsWithTable();

    return $host;
}

beforeEach(function (): void {
    // name asc = [A, B, C] = ids [1, 2, 3]; priority asc = ids [2, 3, 1].
    Node::create(['name' => 'A', 'priority' => 3]);
    Node::create(['name' => 'B', 'priority' => 1]);
    Node::create(['name' => 'C', 'priority' => 2]);
});

it('orders siblings by the configured defaultSort when no column sort is active', function (): void {
    $ids = bootSortableHost()->getFilteredSortedTableQuery()->pluck('id')->all();

    expect($ids)->toBe([2, 3, 1]); // workflow/priority order, not id or name order
});

it('honours a ->sortable(query:) closure for sibling order, delegating to the column', function (): void {
    $host = bootSortableHost();
    $host->tableSort = 'name:asc'; // the name column sorts via its priority query

    $ids = $host->getFilteredSortedTableQuery()->pluck('id')->all();

    expect($ids)->toBe([2, 3, 1])  // priority order produced by the closure...
        ->not->toBe([1, 2, 3]);    // ...not the alphabetical name order
});

it('respects the sort direction when delegating to the column', function (): void {
    $host = bootSortableHost();
    $host->tableSort = 'name:desc';

    $ids = $host->getFilteredSortedTableQuery()->pluck('id')->all();

    expect($ids)->toBe([1, 3, 2]); // priority desc
});
