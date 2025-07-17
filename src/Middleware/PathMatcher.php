<?php

namespace MonkeysLegion\Http\Middleware;

final class PathMatcher
{
    /**
     * Checks if the given path matches any of the provided patterns.
     *
     * @param string $path The path to check.
     * @param array $patterns An array of patterns to match against.
     * @return bool True if the path matches any pattern, false otherwise.
     */
    public static function isMatch(string $path, array $patterns): bool
    {
        foreach ($patterns as $pattern) {
            if ($pattern === '*' || $pattern === '/*') {
                return true;
            }
            if (str_ends_with($pattern, '*')) {
                if (str_starts_with($path, rtrim($pattern, '*'))) {
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