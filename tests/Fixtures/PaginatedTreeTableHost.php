<?php

declare(strict_types=1);

namespace Kisame76\FilamentTreeTable\Tests\Fixtures;

use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Tables\TableComponent;
use Illuminate\Contracts\View\View;
use Kisame76\FilamentTreeTable\Concerns\InteractsWithExpandableRows;
use Kisame76\FilamentTreeTable\Contracts\HasExpandableRows;
use Kisame76\FilamentTreeTable\ExpandableRows;

/**
 * Host that paginates by root node. $paginate flips ->paginateByRoot() so the same
 * fixture covers both the opt-in (root pagination) and the default (row pagination)
 * behaviour.
 */
class PaginatedTreeTableHost extends TableComponent implements HasExpandableRows
{
    use InteractsWithExpandableRows;

    public bool $paginate = true;

    public bool $paginated = true;

    public function table(Table $table): Table
    {
        return ExpandableRows::make()
            ->parentKey('parent_id')
            ->childrenRelationship('children')
            ->expandAllAction(false)
            ->collapseAllAction(false)
            ->paginateByRoot($this->paginate)
            ->defaultSort('id')
            ->applyTo(
                $table
                    ->query(Node::query())
                    ->paginated($this->paginated)
                    ->paginationPageOptions([2, 5, 10])
                    ->columns([
                        TextColumn::make('name'),
                        TextColumn::make('id'),
                    ])
            );
    }

    public function render(): View
    {
        return view('host');
    }
}
