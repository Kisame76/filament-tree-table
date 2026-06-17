<?php

declare(strict_types=1);

namespace Kisame76\FilamentTreeTable\Tests\Fixtures;

use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Tables\TableComponent;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Kisame76\FilamentTreeTable\Concerns\InteractsWithExpandableRows;
use Kisame76\FilamentTreeTable\Contracts\HasExpandableRows;
use Kisame76\FilamentTreeTable\ExpandableRows;

/**
 * Host whose sibling order is driven by the `priority` column, not the displayed
 * `name`. The name column is sorted through a ->sortable(query:) closure and the
 * tree gets a matching defaultSort(), so both the "no active sort" and the "user
 * sorted by a relationship/computed column" paths can be asserted.
 */
class SortableTreeTableHost extends TableComponent implements HasExpandableRows
{
    use InteractsWithExpandableRows;

    public function table(Table $table): Table
    {
        return ExpandableRows::make()
            ->parentKey('parent_id')
            ->childrenRelationship('children')
            ->expandAllAction(false)
            ->collapseAllAction(false)
            ->defaultSort(fn (Builder $query, string $direction): Builder => $query->orderBy('priority', $direction))
            ->applyTo(
                $table
                    ->query(Node::query())
                    ->columns([
                        TextColumn::make('name')
                            ->sortable(query: fn (Builder $query, string $direction): Builder => $query->orderBy('priority', $direction)),
                        TextColumn::make('id'),
                    ])
            );
    }

    public function render(): View
    {
        return view('host');
    }
}
