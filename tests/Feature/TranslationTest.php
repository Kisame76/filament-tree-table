<?php

declare(strict_types=1);

use Illuminate\Support\Facades\App;

it('resolves the expand/collapse-all action labels in the default locale', function (): void {
    expect(__('filament-tree-table::tree-table.actions.expand_all'))->toBe('Expand all')
        ->and(__('filament-tree-table::tree-table.actions.collapse_all'))->toBe('Collapse all');
});

it('translates the action labels when the locale changes', function (): void {
    App::setLocale('de');

    expect(__('filament-tree-table::tree-table.actions.expand_all'))->toBe('Alle ausklappen')
        ->and(__('filament-tree-table::tree-table.actions.collapse_all'))->toBe('Alle einklappen');
});
