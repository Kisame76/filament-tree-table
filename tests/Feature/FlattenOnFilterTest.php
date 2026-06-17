<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Kisame76\FilamentTreeTable\Tests\Fixtures\FilterTreeTableHost;
use Kisame76\FilamentTreeTable\Tests\Fixtures\Node;

uses(RefreshDatabase::class);

function bootFilterHost(bool $keepTree, bool $filterActive = true): FilterTreeTableHost
{
    $host = new FilterTreeTableHost;
    $host->keepTreeOnFilter = $keepTree;
    $host->bootedInteractsWithTable();

    if ($filterActive) {
        $host->tableFilters = ['only_active' => ['isActive' => true]];
    }

    return $host;
}

it('flattens to the matching rows when a filter is active (default)', function (): void {
    $root = Node::create(['name' => 'root', 'archived' => false]);
    $child = Node::create(['name' => 'child', 'archived' => false, 'parent_id' => $root->id]);

    $host = bootFilterHost(keepTree: false);
    $ids = $host->getFilteredSortedTableQuery()->pluck('id')->all();

    // Flat list of all matches, no hierarchy tracked.
    expect($ids)->toContain($root->id)
        ->toContain($child->id)
        ->and($host->getRowDepthMap())->toBe([]);
});

it('keeps the tree and reveals matches with their ancestors when flattenOnFilter is off', function (): void {
    $root = Node::create(['name' => 'root', 'archived' => true]);   // ancestor, not itself a match
    $match = Node::create(['name' => 'match', 'archived' => false, 'parent_id' => $root->id]);
    $other = Node::create(['name' => 'other', 'archived' => true, 'parent_id' => $root->id]);

    $host = bootFilterHost(keepTree: true);
    $ids = $host->getFilteredSortedTableQuery()->pluck('id')->all();

    // Match revealed together with its (non-matching) ancestor, which is auto-expanded;
    // the non-matching sibling stays hidden. Hierarchy (depth) is preserved.
    expect($ids)->toContain($root->id)
        ->toContain($match->id)
        ->not->toContain($other->id)
        ->and($host->getRowDepth($match->id))->toBe(1);
});

it('reveals search matches together with their ancestors when flattenOnSearch is off', function (): void {
    $root = Node::create(['name' => 'root', 'archived' => false]);
    $match = Node::create(['name' => 'needle', 'archived' => false, 'parent_id' => $root->id]);
    $other = Node::create(['name' => 'haystack', 'archived' => false, 'parent_id' => $root->id]);

    $host = new FilterTreeTableHost;
    $host->keepTreeOnFilter = true; // flips flattenOnFilter + flattenOnSearch off
    $host->bootedInteractsWithTable();
    $host->tableSearch = 'needle';

    $ids = $host->getFilteredSortedTableQuery()->pluck('id')->all();

    expect($ids)->toContain($root->id)        // ancestor pulled in for context
        ->toContain($match->id)               // the search match
        ->not->toContain($other->id)          // unrelated sibling hidden
        ->and($host->getRowDepth($match->id))->toBe(1);
});

it('locks expansion and reflects auto-expanded ancestors in the chevron state', function (): void {
    $root = Node::create(['name' => 'root', 'archived' => true]);
    Node::create(['name' => 'needle', 'archived' => false, 'parent_id' => $root->id]);

    $host = new FilterTreeTableHost;
    $host->keepTreeOnFilter = true;
    $host->bootedInteractsWithTable();
    $host->tableFilters = ['only_active' => ['isActive' => true]];

    $host->getFilteredSortedTableQuery(); // build triggers the tree scope

    // Expansion is filter-driven: locked, and the auto-expanded ancestor reads as expanded
    // so its chevron matches what is rendered.
    expect($host->isExpansionLocked())->toBeTrue()
        ->and($host->isRowEffectivelyExpanded($root->id))->toBeTrue();
});

it('does not lock expansion for a plain (unfiltered) tree', function (): void {
    $root = Node::create(['name' => 'root', 'archived' => false]);
    Node::create(['name' => 'child', 'archived' => false, 'parent_id' => $root->id]);

    $host = new FilterTreeTableHost;
    $host->keepTreeOnFilter = true;
    $host->bootedInteractsWithTable();

    $host->getFilteredSortedTableQuery();

    expect($host->isExpansionLocked())->toBeFalse()
        ->and($host->isRowEffectivelyExpanded($root->id))->toBeFalse(); // collapsed by default
});

it('marks non-matching ancestors as dimmed context, but not the matches', function (): void {
    $root = Node::create(['name' => 'root', 'archived' => true]);   // shown only as path context
    $match = Node::create(['name' => 'needle', 'archived' => false, 'parent_id' => $root->id]);

    $host = new FilterTreeTableHost;
    $host->keepTreeOnFilter = true;
    $host->bootedInteractsWithTable();
    $host->tableFilters = ['only_active' => ['isActive' => true]];

    $host->getFilteredSortedTableQuery();

    expect($host->isRowContext($root->id))->toBeTrue()
        ->and($host->isRowContext($match->id))->toBeFalse();
});

it('does not auto-expand when no filter is active (plain collapsed tree)', function (): void {
    $root = Node::create(['name' => 'root', 'archived' => false]);
    $child = Node::create(['name' => 'child', 'archived' => false, 'parent_id' => $root->id]);

    $host = bootFilterHost(keepTree: true, filterActive: false);
    $ids = $host->getFilteredSortedTableQuery()->pluck('id')->all();

    // No filter → normal collapsed tree: the child stays hidden under its parent.
    expect($ids)->toContain($root->id)
        ->not->toContain($child->id);
});
