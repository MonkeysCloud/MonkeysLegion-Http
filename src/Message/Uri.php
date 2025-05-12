<?php
declare(strict_types=1);

namespace MonkeysLegion\Http\Message;

use Psr\Http\Message\UriInterface;

final class Uri implements UriInterface
{
    private string $scheme   = '';
    private string $userInfo = '';
    private string $host     = '';
    private ?int   $port     = null;
    private string $path     = '';
    private string $query    = '';
    private string $fragment = '';

    public function __construct(string $uri = '')
    {
        if ($uri !== '') {
            $parts = parse_url($uri);
            if ($parts === false) {
                throw new \InvalidArgumentException("Unable to parse URI: {$uri}");
            }
            $this->scheme   = $parts['scheme']    ?? '';
            $this->userInfo = ($parts['user']      ?? '')
                . (isset($parts['pass']) ? ':' . $parts['pass'] : '');
            $this->host     = $parts['host']      ?? '';
            $this->port     = $parts['port']      ?? null;
            $this->path     = $parts['path']      ?? '';
            $this->query    = $parts['query']     ?? '';
            $this->fragment = $parts['fragment']  ?? '';
        }
    }

    public function getScheme(): string       { return $this->scheme; }
    public function getAuthority(): string
    {
        $authority = $this->host;
        if ($this->userInfo !== '') {
            $authority = $this->userInfo . '@' . $authority;
        }
        if ($this->port !== null) {
            $authority .= ':' . $this->port;
        }
        return $authority;
    }
    public function getUserInfo(): string    { return $this->userInfo; }
    public function getHost(): string        { return $this->host; }
    public function getPort(): ?int          { return $this->port; }
    public function getPath(): string        { return $this->path; }
    public function getQuery(): string       { return $this->query; }
    public function getFragment(): string    { return $this->fragment; }

    public function withScheme($scheme): static
    {
        $new = clone $this; $new->scheme = $scheme; return $new;
    }
    public function withUserInfo($user, $password = null): static
    {
        $new = clone $this;
        $new->userInfo = $user . ($password !== null ? ':' . $password : '');
        return $new;
    }
    public function withHost($host): static
    {
        $new = clone $this; $new->host = $host; return $new;
    }
    public function withPort($port): static
    {
        $new = clone $this; $new->port = $port; return $new;
    }
    public function withPath($path): static
    {
        $new = clone $this; $new->path = $path; return $new;
    }
    public function withQuery($query): static
    {
        $new = clone $this; $new->query = ltrim($query, '?'); return $new;
    }
    public function withFragment($fragment): static
    {
        $new = clone $this; $new->fragment = ltrim($fragment, '#'); return $new;
    }

    public function __toString(): string
    {
        $uri = '';
        if ($this->scheme !== '') {
            $uri .= $this->scheme . '://';
        }
        $uri .= $this->getAuthority() . $this->path;
        if ($this->query !== '') {
            $uri .= '?' . $this->query;
        }
        if ($this->fragment !== '') {
            $uri .= '#' . $this->fragment;
        }
        return $uri;
    }
}