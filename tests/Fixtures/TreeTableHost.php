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
 * Minimal table host mirroring the documented usage: InteractsWithTable lives on the
 * {@see TableComponent} base class, the tree concern is added on the child. Reorderable +
 * session-persisted columns are on so the column-manager paths are exercised.
 */
class TreeTableHost extends TableComponent implements HasExpandableRows
{
    use InteractsWithExpandableRows;

    public function table(Table $table): Table
    {
        return ExpandableRows::make()
            ->parentKey('parent_id')
            ->childrenRelationship('children')
            ->expandAllAction(false)
            ->collapseAllAction(false)
            ->applyTo(
                $table
                    ->query(Node::query())
                    ->reorderableColumns()
                    ->persistColumnsInSession()
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
