<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Kisame76\FilamentTreeTable\Support\OrderByIds;
use Kisame76\FilamentTreeTable\Support\TreeBuilder;
use Kisame76\FilamentTreeTable\Tests\Fixtures\Node;

uses(RefreshDatabase::class);

it('orders a real query by an explicit key list (CASE fallback on sqlite)', function () {
    foreach (range(1, 5) as $i) {
        Node::create(['name' => "n{$i}"]);
    }

    $ordered = [4, 2, 5, 1, 3];

    $result = OrderByIds::applyTo(
        Node::query()->whereKey($ordered),
        'nodes.id',
        $ordered,
    )->pluck('id')->all();

    expect($result)->toBe($ordered);
});

it('produces a tree order from a real parent/child fetch', function () {
    $root = Node::create(['name' => 'root']);
    $a = Node::create(['name' => 'a', 'parent_id' => $root->id]);
    $b = Node::create(['name' => 'b', 'parent_id' => $root->id]);
    $aChild = Node::create(['name' => 'a-child', 'parent_id' => $a->id]);

    $pairs = Node::query()
        ->orderBy('id')
        ->get(['id', 'parent_id'])
        ->map(fn (Node $node): array => [$node->id, $node->parent_id]);

    $tree = TreeBuilder::build($pairs, [$root->id, $a->id]);

    expect($tree['ordered'])->toBe([
        (string) $root->id,
        (string) $a->id,
        (string) $aChild->id,
        (string) $b->id,
    ]);
});
