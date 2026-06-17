<?php

declare(strict_types=1);

use Kisame76\FilamentTreeTable\Support\TreeBuilder;

// id => parent_id; a two-level tree with a grandchild under node 2.
function sampleRows(): array
{
    return [
        [1, null],
        [2, 1],
        [3, 1],
        [4, 2],
        [5, null],
    ];
}

it('shows only roots when nothing is expanded', function () {
    $tree = TreeBuilder::build(sampleRows(), []);

    expect($tree['ordered'])->toBe(['1', '5'])
        ->and($tree['depth'])->toBe(['1' => 0, '5' => 0]);
});

it('reveals direct children of an expanded node, in order', function () {
    $tree = TreeBuilder::build(sampleRows(), [1]);

    expect($tree['ordered'])->toBe(['1', '2', '3', '5'])
        ->and($tree['depth'])->toBe(['1' => 0, '2' => 1, '3' => 1, '5' => 0]);
});

it('recursively reveals grandchildren and places each child after its parent', function () {
    $tree = TreeBuilder::build(sampleRows(), [1, 2]);

    expect($tree['ordered'])->toBe(['1', '2', '4', '3', '5'])
        ->and($tree['depth']['4'])->toBe(2);
});

it('lists every node that has at least one child', function () {
    $tree = TreeBuilder::build(sampleRows(), []);

    expect($tree['parentsWithChildren'])->toEqualCanonicalizing(['1', '2']);
});

it('does not loop forever on a cyclic parent chain', function () {
    $tree = TreeBuilder::build([[1, 2], [2, 1]], [1, 2]);

    expect($tree['ordered'])->toBeArray();
});

it('returns empty structures for no rows', function () {
    $tree = TreeBuilder::build([], []);

    expect($tree['ordered'])->toBe([])
        ->and($tree['parentsWithChildren'])->toBe([]);
});

it('preserves the incoming row order for roots and children', function () {
    // Rows pre-ordered so root 5 precedes root 1, and node 3 precedes node 2 under root 1.
    $rows = [[5, null], [1, null], [3, 1], [2, 1], [4, 2]];

    $tree = TreeBuilder::build($rows, [1, 2]);

    expect($tree['ordered'])->toBe(['5', '1', '3', '2', '4']);
});
