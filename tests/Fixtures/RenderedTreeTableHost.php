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
 * A host that draws its table. The other fixtures return an empty view and are read
 * through their queries, which is the right level for ordering and filtering rules but
 * never runs Filament's own table rendering - the part a Filament release changes under
 * this package. This one mounts through Livewire and renders, with both header actions on.
 */
class RenderedTreeTableHost extends TableComponent implements HasExpandableRows
{
    use InteractsWithExpandableRows;

    public function table(Table $table): Table
    {
        return ExpandableRows::make()
            ->parentKey('parent_id')
            ->childrenRelationship('children')
            ->applyTo(
                $table
                    ->query(Node::query())
                    ->columns([
                        TextColumn::make('name')->searchable(),
                    ])
            );
    }

    public function render(): View
    {
        return view('rendered-host');
    }
}
