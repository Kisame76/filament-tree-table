<?php

declare(strict_types=1);

namespace Kisame76\FilamentTreeTable\Support;

use Illuminate\Pagination\LengthAwarePaginator;

/**
 * Length-aware paginator whose "Showing X to Y of Z" summary counts *root* nodes,
 * not rows. The page still carries every visible descendant row (so families stay
 * whole), but the total/perPage/current-page maths is driven by the roots.
 *
 * The collection it wraps holds all the rows on the page (roots + descendants); only
 * the summary indices are remapped. {@see firstItem()} / {@see lastItem()} therefore
 * key off the number of roots on the page rather than the (larger) row count, so the
 * range reads e.g. "Showing 1 to 10 of 42" in roots while many more rows render.
 */
class RootPaginator extends LengthAwarePaginator
{
    /**
     * How many root nodes are present on the current page. Drives the summary range
     * so it never exceeds the root total even though the page renders descendant rows.
     */
    protected int $rootsOnPage = 0;

    public function setRootsOnPage(int $rootsOnPage): static
    {
        $this->rootsOnPage = $rootsOnPage;

        return $this;
    }

    /**
     * First root index on the page. perPage is roots-per-page, so the default
     * `(currentPage - 1) * perPage + 1` is already a root index; only the empty-page
     * guard is swapped to the root count.
     *
     * @return int|null
     */
    public function firstItem()
    {
        return $this->rootsOnPage > 0 ? ($this->currentPage - 1) * $this->perPage + 1 : null;
    }

    /**
     * Last root index on the page. The parent adds `count($items) - 1` (the row count);
     * here we add the root count so "to Y" stays within the root total.
     *
     * @return int|null
     */
    public function lastItem()
    {
        return $this->rootsOnPage > 0 ? $this->firstItem() + $this->rootsOnPage - 1 : null;
    }
}
