<?php

declare(strict_types=1);

namespace Kisame76\FilamentTreeTable;

use Filament\Contracts\Plugin;
use Filament\Panel;

/**
 * Optional panel plugin. Registering it is NOT required — the feature is wired per
 * table via {@see ExpandableRows} and the package CSS auto-registers in the service
 * provider. It exists so consumers who prefer the panel-plugin convention can write
 * `->plugin(FilamentTreeTablePlugin::make())`.
 */
class FilamentTreeTablePlugin implements Plugin
{
    public function getId(): string
    {
        return 'filament-tree-table';
    }

    public function register(Panel $panel): void
    {
        //
    }

    public function boot(Panel $panel): void
    {
        //
    }

    public static function make(): static
    {
        return app(static::class);
    }

    public static function get(): static
    {
        /** @var static $plugin */
        $plugin = filament(app(static::class)->getId());

        return $plugin;
    }
}
