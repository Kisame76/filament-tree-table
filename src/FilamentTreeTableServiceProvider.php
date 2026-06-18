<?php

declare(strict_types=1);

namespace Kisame76\FilamentTreeTable;

use Filament\Support\Assets\Css;
use Filament\Support\Facades\FilamentAsset;
use Filament\Support\Facades\FilamentView;
use Filament\View\PanelsRenderHook;
use Illuminate\Support\HtmlString;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class FilamentTreeTableServiceProvider extends PackageServiceProvider
{
    public static string $name = 'filament-tree-table';

    public function configurePackage(Package $package): void
    {
        $package
            ->name(static::$name)
            ->hasConfigFile()
            ->hasTranslations();
    }

    public function packageBooted(): void
    {
        FilamentAsset::register(
            [
                Css::make('filament-tree-table', __DIR__.'/../resources/dist/filament-tree-table.css'),
            ],
            'kisame76/filament-tree-table',
        );

        $this->registerColorOverrides();
    }

    /**
     * Inject the configured accent / tint colours as CSS custom properties so they can
     * be changed from config alone — no theme CSS, no asset rebuild, no cache busting.
     */
    protected function registerColorOverrides(): void
    {
        $vars = collect([
            '--ftt-accent-color' => config('filament-tree-table.accent_color'),
            '--ftt-tint-color' => config('filament-tree-table.tint_color'),
        ])->filter()->map(fn ($value, $name): string => "{$name}: {$value};")->implode(' ');

        if ($vars === '') {
            return;
        }

        FilamentView::registerRenderHook(
            PanelsRenderHook::STYLES_AFTER,
            fn (): HtmlString => new HtmlString('<style>:root{'.$vars.'}</style>'),
        );
    }
}
