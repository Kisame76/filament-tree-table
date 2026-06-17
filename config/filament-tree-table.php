<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Tree structure
    |--------------------------------------------------------------------------
    | The foreign key that points a row at its parent, and the HasMany relationship
    | used to count children (for the chevron) and walk the tree.
    */
    'parent_key' => 'parent_id',
    'children_relationship' => 'children',

    /*
    |--------------------------------------------------------------------------
    | Sub-row appearance
    |--------------------------------------------------------------------------
    |   grid         — the per-level stepping (indentation). On = one fixed icon-column
    |                  per depth (width = --ftt-slot CSS var, default 1.25rem); off = flat
    |                  rows with no indentation (hierarchy shown by tint/accent). Toggled
    |                  independently of the corner arrow.
    |   corner_arrow — a corner-down-right glyph on each child row
    |   accent_bar   — a coloured bar on the child row's left edge
    |   depth_tint   — a per-depth background tint (child rows render lighter)
    */
    'grid' => true,
    'corner_arrow' => true,
    'accent_bar' => true,
    'depth_tint' => true,

    /*
    |--------------------------------------------------------------------------
    | Colours
    |--------------------------------------------------------------------------
    | Any CSS colour value, set to override without touching theme CSS. null keeps the
    | defaults: the accent bar uses the Filament panel primary colour, the tint stays
    | neutral and adapts to light/dark mode. Examples: '#f59e0b', 'rgb(34 197 94)'.
    */
    'accent_color' => null,
    'tint_color' => null,

    /*
    |--------------------------------------------------------------------------
    | Header actions
    |--------------------------------------------------------------------------
    */
    'expand_all_action' => true,
    'collapse_all_action' => true,

    /*
    |--------------------------------------------------------------------------
    | Flatten on sort
    |--------------------------------------------------------------------------
    | When true, clicking a sortable column header drops the tree and shows a flat
    | sorted list. When false, the tree is kept and the sort is applied hierarchically
    | (Jira-style): every sibling group follows the column while staying grouped under
    | its parent. (A relationship/computed sort column falls back to natural key order.)
    */
    'flatten_on_sort' => false,
];
