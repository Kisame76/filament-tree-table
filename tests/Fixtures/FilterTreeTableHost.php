<?php

declare(strict_types=1);

namespace Kisame76\FilamentTreeTable\Tests\Fixtures;

use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Filament\Tables\TableComponent;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Kisame76\FilamentTreeTable\Concerns\InteractsWithExpandableRows;
use Kisame76\FilamentTreeTable\Contracts\HasExpandableRows;
use Kisame76\FilamentTreeTable\ExpandableRows;

/**
 * Host with an "only active" filter. $keepTreeOnFilter flips flattenOnFilter() so the
 * same fixture exercises both the default (filter flattens) and the opt-out (filter
 * keeps the hierarchy) behaviour.
 */
class FilterTreeTableHost extends TableComponent implements HasExpandableRows
{
    use InteractsWithExpandableRows;

    public bool $keepTreeOnFilter = false;

    public function table(Table $table): Table
    {
        return ExpandableRows::make()
            ->parentKey('parent_id')
            ->childrenRelationship('children')
            ->expandAllAction(false)
            ->collapseAllAction(false)
            ->flattenOnFilter(! $this->keepTreeOnFilter)
            ->flattenOnSearch(! $this->keepTreeOnFilter)
            ->applyTo(
                $table
                    ->query(Node::query())
                    ->filters([
                        Filter::make('only_active')
                            ->query(fn (Builder $query): Builder => $query->where('archived', false)),
                    ])
                    ->columns([
                        TextColumn::make('name')->searchable(),
                        TextColumn::make('id'),
                    ])
            );
    }

    public function render(): View
    {
        return view('host');
    }
}
