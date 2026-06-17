<?php

declare(strict_types=1);

namespace Kisame76\FilamentTreeTable\Support;

use Illuminate\Database\Eloquent\Builder;

/**
 * Orders a query by an explicit, pre-computed list of primary keys, portably
 * across database drivers:
 *  - pgsql           → array_position(ARRAY[...], key)
 *  - mysql / mariadb → FIELD(key, ...)
 *  - everything else → CASE key WHEN ... THEN <pos> ... END   (sqlite, sqlsrv, ...)
 *
 * Numeric keys are inlined as integers (safe). Non-numeric keys (ULID/UUID) are
 * bound as parameters so nothing user-controlled is ever interpolated into SQL.
 */
final class OrderByIds
{
    /**
     * @param  array<int, int|string>  $orderedKeys
     */
    public static function applyTo(Builder $query, string $keyColumn, array $orderedKeys): Builder
    {
        if ($orderedKeys === []) {
            return $query;
        }

        $connection = $query->getModel()->getConnection();
        $wrappedColumn = $connection->getQueryGrammar()->wrap($keyColumn);

        [$sql, $bindings] = self::sql($connection->getDriverName(), $wrappedColumn, $orderedKeys);

        return $query->orderByRaw($sql, $bindings);
    }

    /**
     * Build the raw ORDER BY expression + bindings for a driver. Pure (no DB) so it
     * can be unit-tested for every driver without a connection.
     *
     * @param  array<int, int|string>  $orderedKeys
     * @return array{0: string, 1: array<int, int|string>}
     */
    public static function sql(string $driver, string $wrappedColumn, array $orderedKeys): array
    {
        if (self::allNumeric($orderedKeys)) {
            $ids = array_map('intval', $orderedKeys);

            return match ($driver) {
                'pgsql' => ['array_position(ARRAY['.implode(',', $ids).']::bigint[], '.$wrappedColumn.')', []],
                'mysql', 'mariadb' => ['FIELD('.$wrappedColumn.', '.implode(',', $ids).')', []],
                default => [self::inlineCase($wrappedColumn, $ids), []],
            };
        }

        $count = count($orderedKeys);
        $placeholders = implode(',', array_fill(0, $count, '?'));
        $bindings = array_map('strval', $orderedKeys);

        return match ($driver) {
            'pgsql' => ['array_position(ARRAY['.$placeholders.']::text[], '.$wrappedColumn.'::text)', $bindings],
            'mysql', 'mariadb' => ['FIELD('.$wrappedColumn.', '.$placeholders.')', $bindings],
            default => [self::boundCase($wrappedColumn, $count), $bindings],
        };
    }

    /**
     * @param  array<int, int|string>  $keys
     */
    private static function allNumeric(array $keys): bool
    {
        foreach ($keys as $key) {
            if (! is_int($key) && ! (is_string($key) && ctype_digit($key))) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  array<int, int>  $ids
     */
    private static function inlineCase(string $column, array $ids): string
    {
        $whens = '';
        foreach ($ids as $position => $id) {
            $whens .= " WHEN {$id} THEN {$position}";
        }

        return 'CASE '.$column.$whens.' ELSE '.count($ids).' END';
    }

    private static function boundCase(string $column, int $count): string
    {
        $whens = '';
        for ($position = 0; $position < $count; $position++) {
            $whens .= " WHEN ? THEN {$position}";
        }

        return 'CASE '.$column.$whens.' ELSE '.$count.' END';
    }
}
