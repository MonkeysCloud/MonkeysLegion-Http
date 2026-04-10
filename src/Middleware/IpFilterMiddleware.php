<?php
declare(strict_types=1);

namespace MonkeysLegion\Http\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * MonkeysLegion Framework — HTTP Package
 *
 * IP-based access control with whitelist/blacklist and CIDR support.
 *
 * Supports both IPv4 and IPv6 addresses and CIDR notation.
 *
 * @copyright 2026 MonkeysCloud Team
 * @license   MIT
 */
final class IpFilterMiddleware implements MiddlewareInterface
{
    /**
     * @param list<string> $allowList  Allowed IPs/CIDRs (empty = allow all).
     * @param list<string> $denyList   Denied IPs/CIDRs (checked first).
     * @param int          $denyStatus HTTP status for rejected requests.
     */
    public function __construct(
        private readonly array $allowList  = [],
        private readonly array $denyList   = [],
        private readonly int   $denyStatus = 403,
    ) {}

    public function process(
        ServerRequestInterface $request,
        RequestHandlerInterface $handler,
    ): ResponseInterface {
        $ip = $request->getAttribute('client_ip')
            ?? $request->getServerParams()['REMOTE_ADDR']
            ?? '0.0.0.0';

        // Deny list takes priority
        if ($this->denyList !== [] && $this->matchesList($ip, $this->denyList)) {
            return $this->reject();
        }

        // If allow list is defined, IP must be in it
        if ($this->allowList !== [] && !$this->matchesList($ip, $this->allowList)) {
            return $this->reject();
        }

        return $handler->handle($request);
    }

    // ── Internal ───────────────────────────────────────────────

    /**
     * @param list<string> $list
     */
    private function matchesList(string $ip, array $list): bool
    {
        foreach ($list as $entry) {
            if (str_contains($entry, '/')) {
                if ($this->ipInCidr($ip, $entry)) {
                    return true;
                }
            } elseif ($ip === $entry) {
                return true;
            }
        }
        return false;
    }

    private function ipInCidr(string $ip, string $cidr): bool
    {
        [$subnet, $bits] = explode('/', $cidr, 2);
        $bits = (int) $bits;

        $ipBin  = @inet_pton($ip);
        $subBin = @inet_pton($subnet);

        if ($ipBin === false || $subBin === false || strlen($ipBin) !== strlen($subBin)) {
            return false;
        }

        // Build mask and compare
        $byteLen = strlen($ipBin);
        $mask    = str_repeat("\xff", (int) ($bits / 8));
        if ($bits % 8 !== 0) {
            $mask .= chr(0xff << (8 - ($bits % 8)) & 0xff);
        }
        $mask = str_pad($mask, $byteLen, "\x00");

        return ($ipBin & $mask) === ($subBin & $mask);
    }

    private function reject(): ResponseInterface
    {
        $json = json_encode([
            'status'  => 'error',
            'message' => 'Access denied.',
        ], JSON_UNESCAPED_SLASHES);

        return new \MonkeysLegion\Http\Message\Response(
            \MonkeysLegion\Http\Message\Stream::createFromString($json),
            $this->denyStatus,
            ['Content-Type' => 'application/json'],
        );
    }
}
