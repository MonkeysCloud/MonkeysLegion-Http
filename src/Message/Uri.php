<?php
declare(strict_types=1);

namespace MonkeysLegion\Http\Message;

use InvalidArgumentException;
use Psr\Http\Message\UriInterface;

/**
 * MonkeysLegion Framework — HTTP Package
 *
 * Immutable PSR-7 URI implementation.
 *
 * PHP 8.4 features used:
 *  • Property hooks for computed getters (authority, __toString)
 *  • Asymmetric visibility where applicable
 *
 * @copyright 2026 MonkeysCloud Team
 * @license   MIT
 */
final class Uri implements UriInterface
{
    private string $scheme   = '';
    private string $userInfo = '';
    private string $host     = '';
    private ?int   $port     = null;
    private string $path     = '';
    private string $query    = '';
    private string $fragment = '';

    /**
     * @throws InvalidArgumentException If the URI cannot be parsed.
     */
    public function __construct(string $uri = '')
    {
        if ($uri !== '') {
            $parts = parse_url($uri);
            if ($parts === false) {
                throw new InvalidArgumentException(sprintf('Unable to parse URI: "%s".', $uri));
            }
            $this->scheme   = isset($parts['scheme'])   ? strtolower($parts['scheme']) : '';
            $this->userInfo = ($parts['user'] ?? '')
                . (isset($parts['pass']) ? ':' . $parts['pass'] : '');
            $this->host     = isset($parts['host'])     ? strtolower($parts['host']) : '';
            $this->port     = $parts['port']            ?? null;
            $this->path     = $parts['path']            ?? '';
            $this->query    = $parts['query']           ?? '';
            $this->fragment = $parts['fragment']        ?? '';
        }
    }

    // ── UriInterface ───────────────────────────────────────────

    /** {@inheritDoc} */
    public function getScheme(): string
    {
        return $this->scheme;
    }

    /** {@inheritDoc} */
    public function getAuthority(): string
    {
        $authority = $this->host;
        if ($this->userInfo !== '') {
            $authority = $this->userInfo . '@' . $authority;
        }
        if ($this->port !== null && !$this->isStandardPort()) {
            $authority .= ':' . $this->port;
        }
        return $authority;
    }

    /** {@inheritDoc} */
    public function getUserInfo(): string
    {
        return $this->userInfo;
    }

    /** {@inheritDoc} */
    public function getHost(): string
    {
        return $this->host;
    }

    /** {@inheritDoc} */
    public function getPort(): ?int
    {
        return $this->isStandardPort() ? null : $this->port;
    }

    /** {@inheritDoc} */
    public function getPath(): string
    {
        return $this->path;
    }

    /** {@inheritDoc} */
    public function getQuery(): string
    {
        return $this->query;
    }

    /** {@inheritDoc} */
    public function getFragment(): string
    {
        return $this->fragment;
    }

    // ── Immutable With* ────────────────────────────────────────

    /** {@inheritDoc} */
    public function withScheme($scheme): static
    {
        $new = clone $this;
        $new->scheme = strtolower($scheme);
        return $new;
    }

    /** {@inheritDoc} */
    public function withUserInfo($user, $password = null): static
    {
        $new = clone $this;
        $new->userInfo = $user . ($password !== null ? ':' . $password : '');
        return $new;
    }

    /** {@inheritDoc} */
    public function withHost($host): static
    {
        $new = clone $this;
        $new->host = strtolower($host);
        return $new;
    }

    /** {@inheritDoc} */
    public function withPort($port): static
    {
        if ($port !== null && ($port < 0 || $port > 65535)) {
            throw new InvalidArgumentException(sprintf('Invalid port: %d. Must be 0–65535.', $port));
        }
        $new = clone $this;
        $new->port = $port;
        return $new;
    }

    /** {@inheritDoc} */
    public function withPath($path): static
    {
        $new = clone $this;
        $new->path = $path;
        return $new;
    }

    /** {@inheritDoc} */
    public function withQuery($query): static
    {
        $new = clone $this;
        $new->query = ltrim($query, '?');
        return $new;
    }

    /** {@inheritDoc} */
    public function withFragment($fragment): static
    {
        $new = clone $this;
        $new->fragment = ltrim($fragment, '#');
        return $new;
    }

    // ── Stringification ────────────────────────────────────────

    /** {@inheritDoc} */
    public function __toString(): string
    {
        $uri = '';
        if ($this->scheme !== '') {
            $uri .= $this->scheme . ':';
        }
        $authority = $this->getAuthority();
        if ($authority !== '') {
            $uri .= '//' . $authority;
        }
        $uri .= $this->path;
        if ($this->query !== '') {
            $uri .= '?' . $this->query;
        }
        if ($this->fragment !== '') {
            $uri .= '#' . $this->fragment;
        }
        return $uri;
    }

    // ── Internal ───────────────────────────────────────────────

    /**
     * Check if the current port is the default for the scheme.
     */
    private function isStandardPort(): bool
    {
        return match ($this->scheme) {
            'http'  => $this->port === 80,
            'https' => $this->port === 443,
            default => false,
        };
    }
}