<?php

declare(strict_types=1);

use Kisame76\FilamentTreeTable\Support\OrderByIds;

it('builds pgsql array_position ordering for numeric keys', function () {
    [$sql, $bindings] = OrderByIds::sql('pgsql', '"id"', [3, 1, 2]);

    expect($sql)->toBe('array_position(ARRAY[3,1,2]::bigint[], "id")')
        ->and($bindings)->toBe([]);
});

it('builds mysql FIELD ordering for numeric keys', function () {
    [$sql, $bindings] = OrderByIds::sql('mysql', '`id`', [3, 1, 2]);

    expect($sql)->toBe('FIELD(`id`, 3,1,2)')
        ->and($bindings)->toBe([]);
});

it('falls back to a CASE expression for sqlite', function () {
    [$sql, $bindings] = OrderByIds::sql('sqlite', '"id"', [3, 1, 2]);

    expect($sql)->toBe('CASE "id" WHEN 3 THEN 0 WHEN 1 THEN 1 WHEN 2 THEN 2 ELSE 3 END')
        ->and($bindings)->toBe([]);
});

it('binds non-numeric keys instead of inlining them (pgsql)', function () {
    [$sql, $bindings] = OrderByIds::sql('pgsql', '"id"', ['01HABC', '01HXYZ']);

    expect($sql)->toBe('array_position(ARRAY[?,?]::text[], "id"::text)')
        ->and($bindings)->toBe(['01HABC', '01HXYZ']);
});

it('binds non-numeric keys for the CASE fallback', function () {
    [$sql, $bindings] = OrderByIds::sql('sqlite', '"id"', ['a', 'b']);

    expect($sql)->toBe('CASE "id" WHEN ? THEN 0 WHEN ? THEN 1 ELSE 2 END')
        ->and($bindings)->toBe(['a', 'b']);
});
