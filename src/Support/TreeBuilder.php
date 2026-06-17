<?php

declare(strict_types=1);

namespace Kisame76\FilamentTreeTable\Support;

/**
 * Pure, database-agnostic tree maths. Given a flat list of [key, parentKey] pairs
 * and the set of currently expanded keys, it produces the depth-first ordered list
 * of *visible* keys (a node is visible when all of its ancestors are expanded),
 * a [key => depth] map, and the keys of every node that has at least one child.
 *
 * Keys are normalised to strings so int and string (ULID/UUID) primary keys behave
 * identically and never collide through PHP's array-key coercion.
 */
final class TreeBuilder
{
    /**
     * The order of $rows is preserved: roots appear in their incoming order and each
     * parent's children in theirs. Order the rows by a column before calling this to get
     * a hierarchical (Jira-style) sort where every sibling group follows that column.
     *
     * @param  iterable<array{0: int|string, 1: int|string|null}>  $rows  list of [key, parentKey]
     * @param  array<int, int|string>  $expandedKeys
     * @return array{ordered: list<string>, depth: array<string, int>, parentsWithChildren: list<string>}
     */
    public static function build(iterable $rows, array $expandedKeys): array
    {
        /** @var list<string> $roots */
        $roots = [];

        /** @var array<string, list<string>> $childrenByParent */
        $childrenByParent = [];

        foreach ($rows as [$key, $parent]) {
            $key = (string) $key;

            if ($parent === null || $parent === '') {
                $roots[] = $key;

                continue;
            }

            $childrenByParent[(string) $parent][] = $key;
        }

        $expanded = array_fill_keys(array_map('strval', $expandedKeys), true);

        /** @var list<string> $ordered */
        $ordered = [];

        /** @var array<string, int> $depth */
        $depth = [];

        /** @var array<string, true> $visited */
        $visited = [];

        $walk = function (array $keys, int $level) use (&$walk, &$ordered, &$depth, &$visited, $childrenByParent, $expanded): void {
            foreach ($keys as $key) {
                if (isset($visited[$key])) {
                    // Defensive: a malformed parent chain must never loop forever.
                    continue;
                }

                $visited[$key] = true;
                $ordered[] = $key;
                $depth[$key] = $level;

                if (isset($expanded[$key], $childrenByParent[$key])) {
                    $walk($childrenByParent[$key], $level + 1);
                }
            }
        };

        $walk($roots, 0);

        return [
            'ordered' => $ordered,
            'depth' => $depth,
            'parentsWithChildren' => array_keys($childrenByParent),
        ];
    }
}
