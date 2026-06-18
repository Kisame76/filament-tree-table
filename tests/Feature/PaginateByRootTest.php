<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Kisame76\FilamentTreeTable\Support\RootPaginator;
use Kisame76\FilamentTreeTable\Tests\Fixtures\Node;
use Kisame76\FilamentTreeTable\Tests\Fixtures\PaginatedTreeTableHost;

uses(RefreshDatabase::class);

/**
 * Five roots (ids 1..5), each with two children, all expanded. Roots sort by id, so the
 * first roots on a page are the lowest ids; their children carry the higher ids.
 *
 * @return array{0: PaginatedTreeTableHost, 1: Collection<int, Node>}
 */
function bootPaginatedHost(bool $paginateByRoot = true, bool $paginated = true, int|string|null $perPage = 2): array
{
    $roots = collect(range(1, 5))->map(fn (int $i): Node => Node::create([
        'name' => "root {$i}",
        'archived' => false,
    ]));

    foreach ($roots as $root) {
        Node::create(['name' => "child a of {$root->id}", 'archived' => false, 'parent_id' => $root->id]);
        Node::create(['name' => "child b of {$root->id}", 'archived' => false, 'parent_id' => $root->id]);
    }

    $host = new PaginatedTreeTableHost;
    $host->paginate = $paginateByRoot;
    $host->paginated = $paginated;
    $host->bootedInteractsWithTable();

    // Expand every root so its children are part of the visible tree.
    $host->expandedRows = $roots->pluck('id')->map(fn ($id): string => (string) $id)->all();
    $host->tableRecordsPerPage = $perPage;

    return [$host, $roots];
}

/**
 * @return array<int, int>
 */
function pageKeys(PaginatedTreeTableHost $host): array
{
    return collect($host->getTableRecords()->items())
        ->map(fn (Node $node): int => (int) $node->getKey())
        ->sort()
        ->values()
        ->all();
}

function goToPage(PaginatedTreeTableHost $host, int $page): void
{
    $host->flushCachedTableRecords();
    $host->paginators[$host->getTablePaginationPageName()] = $page;
}

it('keeps each family whole and paginates by root', function (): void {
    [$host] = bootPaginatedHost(perPage: 2);

    // Page 1: the two lowest-id roots (1, 2) plus their four children (6, 7, 8, 9).
    expect(pageKeys($host))->toBe([1, 2, 6, 7, 8, 9]);

    $paginator = $host->getTableRecords();

    expect($paginator)->toBeInstanceOf(RootPaginator::class)
        ->and($paginator->total())->toBe(5)            // total = root count
        ->and($paginator->perPage())->toBe(2)          // roots per page
        ->and($paginator->lastPage())->toBe(3)         // ceil(5 / 2)
        ->and($paginator->currentPage())->toBe(1)
        ->and($paginator->firstItem())->toBe(1)        // "Showing 1 to 2 of 5"
        ->and($paginator->lastItem())->toBe(2);
});

it('never splits a child away from its parent on any page', function (): void {
    [$host] = bootPaginatedHost(perPage: 2);

    foreach ([1, 2, 3] as $page) {
        goToPage($host, $page);

        $records = collect($host->getTableRecords()->items());
        $keysOnPage = $records->map(fn (Node $node): int => (int) $node->getKey())->all();

        // Every descendant on the page has its parent on the same page.
        $records
            ->filter(fn (Node $node): bool => $node->parent_id !== null)
            ->each(function (Node $node) use ($keysOnPage): void {
                expect($keysOnPage)->toContain((int) $node->parent_id);
            });
    }
});

it('shows the next root family on the last page with a root-based summary', function (): void {
    [$host] = bootPaginatedHost(perPage: 2);

    goToPage($host, 3);

    expect(pageKeys($host))->toBe([5, 14, 15]);        // root 5 + its two children

    $paginator = $host->getTableRecords();

    expect($paginator->currentPage())->toBe(3)
        ->and($paginator->firstItem())->toBe(5)        // "Showing 5 to 5 of 5"
        ->and($paginator->lastItem())->toBe(5)
        ->and($paginator->total())->toBe(5);
});

it('treats the per-page selection as roots per page', function (): void {
    [$host] = bootPaginatedHost(perPage: 5);

    // All five roots fit on one page → every one of the 15 rows is present.
    expect($host->getTableRecords())->toHaveCount(15)
        ->and($host->getTableRecords()->total())->toBe(5)
        ->and($host->getTableRecords()->lastPage())->toBe(1);
});

it('paginates by row (stock behaviour) when paginateByRoot is off', function (): void {
    [$host] = bootPaginatedHost(paginateByRoot: false, perPage: 2);

    $paginator = $host->getTableRecords();

    // Falls back to Filament's row paginator: 15 total rows, 2 per page.
    expect($paginator)->toBeInstanceOf(LengthAwarePaginator::class)
        ->not->toBeInstanceOf(RootPaginator::class)
        ->and($paginator->total())->toBe(15)
        ->and($paginator)->toHaveCount(2);
});

it('returns the whole tree unpaginated when pagination is disabled', function (): void {
    [$host] = bootPaginatedHost(paginated: false, perPage: 2);

    $records = $host->getTableRecords();

    expect($records)->not->toBeInstanceOf(LengthAwarePaginator::class)
        ->and($records)->toHaveCount(15);
});

it('falls back to row pagination while a collapsed tree has no expanded roots', function (): void {
    [$host] = bootPaginatedHost(perPage: 2);
    $host->expandedRows = []; // collapse everything: only the 5 roots are visible

    // Still root-paginated, but with no children visible each block is a single root.
    expect(pageKeys($host))->toBe([1, 2]);

    expect($host->getTableRecords()->total())->toBe(5);
});
