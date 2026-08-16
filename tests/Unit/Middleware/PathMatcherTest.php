<?php

declare(strict_types=1);

namespace MonkeysLegion\Http\Tests\Unit\Middleware;

use MonkeysLegion\Http\Middleware\PathMatcher;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class PathMatcherTest extends TestCase
{
    #[Test]
    public function matches_exact_paths(): void
    {
        $this->assertTrue(PathMatcher::isMatch('/login', ['/login']));
    }

    #[Test]
    public function global_wildcards_match_everything(): void
    {
        $this->assertTrue(PathMatcher::isMatch('/anything/here', ['*']));
        $this->assertTrue(PathMatcher::isMatch('/anything/here', ['/*']));
    }

    #[Test]
    public function prefix_wildcards_match_subpaths(): void
    {
        $this->assertTrue(PathMatcher::isMatch('/api/users', ['/api/*']));
        $this->assertTrue(PathMatcher::isMatch('/api/users/42', ['/api/*']));
    }

    #[Test]
    public function prefix_wildcard_does_not_match_other_branches(): void
    {
        $this->assertFalse(PathMatcher::isMatch('/admin/users', ['/api/*']));
    }

    #[Test]
    public function fnmatch_patterns_are_supported(): void
    {
        $this->assertTrue(PathMatcher::isMatch('/user/123', ['/user/[0-9]*']));
        $this->assertFalse(PathMatcher::isMatch('/user/abc', ['/user/[0-9]*']));
    }

    #[Test]
    public function matching_is_case_insensitive(): void
    {
        $this->assertTrue(PathMatcher::isMatch('/ADMIN', ['/admin']));
    }

    #[Test]
    public function empty_pattern_list_matches_nothing(): void
    {
        $this->assertFalse(PathMatcher::isMatch('/login', []));
    }
}
