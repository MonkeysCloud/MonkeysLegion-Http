<?php

declare(strict_types=1);

namespace MonkeysLegion\Http\Tests\Unit\Message;

use InvalidArgumentException;
use MonkeysLegion\Http\Message\Uri;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class UriTest extends TestCase
{
    #[Test]
    public function empty_uri_has_defaults(): void
    {
        $uri = new Uri();

        $this->assertSame('', $uri->getScheme());
        $this->assertSame('', $uri->getAuthority());
        $this->assertSame('', $uri->getHost());
        $this->assertNull($uri->getPort());
        $this->assertSame('', $uri->getPath());
        $this->assertSame('', $uri->getQuery());
        $this->assertSame('', $uri->getFragment());
        $this->assertSame('', (string) $uri);
    }

    #[Test]
    public function parses_full_uri_and_lowercases_scheme_and_host(): void
    {
        $uri = new Uri('HTTPS://User:Pass@EXAMPLE.com:8443/some/path?q=1#frag');

        $this->assertSame('https', $uri->getScheme());
        $this->assertSame('example.com', $uri->getHost());
        $this->assertSame('User:Pass', $uri->getUserInfo());
        $this->assertSame(8443, $uri->getPort());
        $this->assertSame('/some/path', $uri->getPath());
        $this->assertSame('q=1', $uri->getQuery());
        $this->assertSame('frag', $uri->getFragment());
        $this->assertSame('https://User:Pass@example.com:8443/some/path?q=1#frag', (string) $uri);
    }

    #[Test]
    public function throws_on_unparseable_uri(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new Uri('http://example.com:99999'); // port out of range → parse_url() fails
    }

    #[Test]
    public function authority_omits_standard_http_port(): void
    {
        $uri = new Uri('http://example.com:80/path');
        $this->assertNull($uri->getPort());
        $this->assertSame('example.com', $uri->getAuthority());
        $this->assertSame('http://example.com/path', (string) $uri);
    }

    #[Test]
    public function authority_omits_standard_https_port(): void
    {
        $uri = new Uri('https://example.com:443/path');
        $this->assertNull($uri->getPort());
        $this->assertSame('example.com', $uri->getAuthority());
        $this->assertSame('https://example.com/path', (string) $uri);
    }

    #[Test]
    public function authority_includes_non_standard_port(): void
    {
        $uri = new Uri('http://example.com:8080/path');
        $this->assertSame(8080, $uri->getPort());
        $this->assertSame('example.com:8080', $uri->getAuthority());
        $this->assertSame('http://example.com:8080/path', (string) $uri);
    }

    #[Test]
    public function port_for_unknown_scheme_is_never_standard(): void
    {
        $uri = new Uri('ftp://example.com:21');
        $this->assertSame(21, $uri->getPort());
        $this->assertSame('example.com:21', $uri->getAuthority());
    }

    #[Test]
    public function authority_includes_userinfo(): void
    {
        $uri = new Uri('http://user@example.com');
        $this->assertSame('user@example.com', $uri->getAuthority());

        $uri2 = new Uri('http://user:pass@example.com');
        $this->assertSame('user:pass@example.com', $uri2->getAuthority());
    }

    #[Test]
    public function with_scheme_lowercases_and_is_immutable(): void
    {
        $uri = new Uri('http://example.com');
        $new = $uri->withScheme('HTTPS');

        $this->assertSame('http', $uri->getScheme());
        $this->assertSame('https', $new->getScheme());
    }

    #[Test]
    public function with_host_lowercases_and_is_immutable(): void
    {
        $uri = new Uri('http://example.com');
        $new = $uri->withHost('EXAMPLE.ORG');

        $this->assertSame('example.com', $uri->getHost());
        $this->assertSame('example.org', $new->getHost());
    }

    #[Test]
    public function with_user_info_handles_optional_password(): void
    {
        $uri = new Uri('http://example.com');
        $this->assertSame('user', $uri->withUserInfo('user')->getUserInfo());
        $this->assertSame('user:pass', $uri->withUserInfo('user', 'pass')->getUserInfo());
    }

    #[Test]
    public function with_port_validates_range_and_is_immutable(): void
    {
        $uri = new Uri('http://example.com');
        $this->assertSame(8080, $uri->withPort(8080)->getPort());
        $this->assertNull($uri->withPort(null)->getPort());
        $this->assertNull($uri->getPort()); // original untouched
    }

    #[Test]
    public function with_port_rejects_out_of_range(): void
    {
        $uri = new Uri('http://example.com');
        $this->expectException(InvalidArgumentException::class);
        $uri->withPort(70000);
    }

    #[Test]
    public function with_port_rejects_negative(): void
    {
        $uri = new Uri('http://example.com');
        $this->expectException(InvalidArgumentException::class);
        $uri->withPort(-1);
    }

    #[Test]
    public function with_port_accepts_boundaries(): void
    {
        $uri = new Uri('http://example.com');
        $this->assertSame(0, $uri->withPort(0)->getPort());
        $this->assertSame(65535, $uri->withPort(65535)->getPort());
    }

    #[Test]
    public function with_query_and_fragment_strip_leading_delimiters(): void
    {
        $uri = new Uri('http://example.com');
        $this->assertSame('a=b', $uri->withQuery('?a=b')->getQuery());
        $this->assertSame('sec', $uri->withFragment('#sec')->getFragment());
    }

    #[Test]
    public function with_path_is_immutable(): void
    {
        $uri = new Uri('http://example.com');
        $new = $uri->withPath('/new');

        $this->assertSame('', $uri->getPath());
        $this->assertSame('/new', $new->getPath());
    }

    #[Test]
    public function stringification_includes_all_components(): void
    {
        $uri = new Uri('https://user:pass@example.com:8443/a/b?x=1&y=2#top');
        $this->assertSame('https://user:pass@example.com:8443/a/b?x=1&y=2#top', (string) $uri);
    }

    #[Test]
    public function stringification_of_path_only_uri(): void
    {
        $uri = new Uri('/just/a/path');
        $this->assertSame('/just/a/path', (string) $uri);
        $this->assertSame('', $uri->getAuthority());
    }
}
