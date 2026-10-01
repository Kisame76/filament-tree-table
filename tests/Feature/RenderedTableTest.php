<?php

declare(strict_types=1);

use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Kisame76\FilamentTreeTable\Tests\Fixtures\Node;
use Kisame76\FilamentTreeTable\Tests\Fixtures\RenderedTreeTableHost;
use Livewire\Livewire;

// Drawn through Livewire::test, so it needs a Filament release that survives that under
// Testbench (4.13 and up); the `lowest` CI job leaves this group out. See run-tests.yml.
uses(RefreshDatabase::class)->group('rendered');

function seedFamily(): array
{
    $root = Node::create(['name' => 'Rootling']);
    $child = Node::create(['name' => 'Childling', 'parent_id' => $root->id]);
    $grandchild = Node::create(['name' => 'Grandchildling', 'parent_id' => $child->id]);
    $loner = Node::create(['name' => 'Lonerling']);

    return [$root, $child, $grandchild, $loner];
}

it('draws only the roots of a collapsed tree', function (): void {
    seedFamily();

    Livewire::test(RenderedTreeTableHost::class)
        ->assertSee('Rootling')
        ->assertSee('Lonerling')
        ->assertDontSee('Childling');
});

it('draws a toggle only on rows that have children', function (): void {
    [$root, , , $loner] = seedFamily();

    $html = Livewire::test(RenderedTreeTableHost::class)->html();

    // One interactive chevron (the root with a child); the loner gets none.
    expect(substr_count($html, 'toggleRowExpansion'))->toBe(1)
        ->and($html)->toContain('ftt-toggle')
        ->and($html)->toContain('ftt-root');
});

it('reveals the children when a chevron is toggled, and hides them again', function (): void {
    [$root] = seedFamily();

    Livewire::test(RenderedTreeTableHost::class)
        ->call('toggleRowExpansion', $root->id)
        ->assertSee('Childling')
        ->assertDontSee('Grandchildling')
        ->assertSeeHtml('ftt-child')
        ->assertSeeHtml('ftt-depth-1')
        ->call('toggleRowExpansion', $root->id)
        ->assertDontSee('Childling');
});

it('expands and collapses every row from the header actions', function (): void {
    seedFamily();

    Livewire::test(RenderedTreeTableHost::class)
        ->assertActionVisible(TestAction::make('expandAllRows')->table())
        ->callAction(TestAction::make('expandAllRows')->table())
        ->assertSee('Childling')
        ->assertSee('Grandchildling')
        ->callAction(TestAction::make('collapseAllRows')->table())
        ->assertDontSee('Childling');
});

it('flattens to the matching rows while searching, then returns to the tree', function (): void {
    seedFamily();

    Livewire::test(RenderedTreeTableHost::class)
        ->set('tableSearch', 'Grandchildling')
        ->assertSee('Grandchildling')
        ->assertDontSee('Lonerling')
        ->set('tableSearch', '')
        ->assertSee('Lonerling')
        ->assertDontSee('Grandchildling');
});

it('keeps the expanded rows across a re-render', function (): void {
    [$root] = seedFamily();

    Livewire::test(RenderedTreeTableHost::class)
        ->call('toggleRowExpansion', $root->id)
        ->call('$refresh')
        ->assertSee('Childling');
});
