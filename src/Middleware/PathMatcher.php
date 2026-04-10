<?php
declare(strict_types=1);

namespace MonkeysLegion\Http\Middleware;

/**
 * MonkeysLegion Framework — HTTP Package
 *
 * URI path pattern matcher for middleware exclusion lists.
 *
 * Supports exact paths, wildcard suffixes, and fnmatch() patterns.
 *
 * @copyright 2026 MonkeysCloud Team
 * @license   MIT
 */
final class PathMatcher
{
    /**
     * Check if a path matches any of the given patterns.
     *
     * @param string       $path     The request URI path.
     * @param list<string> $patterns Patterns to match (exact, prefix*, or fnmatch).
     */
    public static function isMatch(string $path, array $patterns): bool
    {
        foreach ($patterns as $pattern) {
            if ($pattern === '*' || $pattern === '/*') {
                return true;
            }

            if (str_ends_with($pattern, '*')) {
                $prefix = rtrim($pattern, '*');
                if (str_starts_with($path, $prefix)) {
                    return true;
                }
            }

            if ($pattern === $path || fnmatch($pattern, $path, FNM_CASEFOLD)) {
                return true;
            }
        }

        return false;
    }
}