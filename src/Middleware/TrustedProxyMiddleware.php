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
 * Resolves the real client IP from trusted proxy headers.
 *
 * Prevents IP spoofing by only trusting X-Forwarded-For, CF-Connecting-IP,
 * etc. when the direct connection comes from a trusted proxy IP/CIDR.
 *
 * Sets the `client_ip` request attribute for downstream consumers.
 *
 * @copyright 2026 MonkeysCloud Team
 * @license   MIT
 */
final class TrustedProxyMiddleware implements MiddlewareInterface
{
    /** Headers to check for forwarded IP, in priority order. */
    private const array FORWARDED_HEADERS = [
        'CF-Connecting-IP',          // Cloudflare
        'X-Forwarded-For',           // Standard proxy
        'X-Real-Ip',                 // Nginx
        'Forwarded',                 // RFC 7239
    ];

    /**
     * @param list<string> $trustedProxies Trusted proxy IPs or CIDRs.
     * @param string       $attributeName  Request attribute name for resolved IP.
     */
    public function __construct(
        private readonly array  $trustedProxies = ['127.0.0.1', '::1'],
        private readonly string $attributeName  = 'client_ip',
    ) {}

    public function process(
        ServerRequestInterface $request,
        RequestHandlerInterface $handler,
    ): ResponseInterface {
        $remoteAddr = $request->getServerParams()['REMOTE_ADDR'] ?? '0.0.0.0';
        $clientIp   = $remoteAddr;

        // Only trust forwarded headers if direct connection is from a trusted proxy
        if ($this->isTrusted($remoteAddr)) {
            foreach (self::FORWARDED_HEADERS as $header) {
                $value = $request->getHeaderLine($header);
                if ($value === '') {
                    continue;
                }

                // Handle Forwarded: for=1.2.3.4 (RFC 7239)
                if (strcasecmp($header, 'Forwarded') === 0) {
                    if (preg_match('/for\s*=\s*"?([^";,\s]+)/i', $value, $m)) {
                        $clientIp = trim($m[1], '"[]');
                        break;
                    }
                    continue;
                }

                // X-Forwarded-For: client, proxy1, proxy2 → take first
                $ips = explode(',', $value);
                $clientIp = trim($ips[0]);
                break;
            }
        }

        // Validate resolved IP
        if (filter_var($clientIp, FILTER_VALIDATE_IP) === false) {
            $clientIp = $remoteAddr;
        }

        $request = $request->withAttribute($this->attributeName, $clientIp);

        return $handler->handle($request);
    }

    // ── Internal ───────────────────────────────────────────────

    private function isTrusted(string $ip): bool
    {
        foreach ($this->trustedProxies as $trusted) {
            if (str_contains($trusted, '/')) {
                if ($this->ipInCidr($ip, $trusted)) {
                    return true;
                }
            } elseif ($ip === $trusted) {
                return true;
            }
        }
        return false;
    }

    /**
     * Check if an IP falls within a CIDR block.
     */
    private function ipInCidr(string $ip, string $cidr): bool
    {
        [$subnet, $bits] = explode('/', $cidr, 2);
        $bits = (int) $bits;

        $ipBin    = inet_pton($ip);
        $subBin   = inet_pton($subnet);

        if ($ipBin === false || $subBin === false || strlen($ipBin) !== strlen($subBin)) {
            return false;
        }

        $ipLong  = $this->ipToLong($ipBin);
        $subLong = $this->ipToLong($subBin);

        $totalBits = strlen($ipBin) * 8;
        $mask      = $bits === 0 ? '0' : bcpow('2', (string) $totalBits);
        $mask      = bcsub($mask, bcpow('2', (string) ($totalBits - $bits)));

        return bccomp(
            bcmod(bcadd($ipLong, '0'), bcpow('2', (string) $totalBits)),
            '0',
        ) >= 0
        && (($this->bitwiseAnd($ipLong, $mask)) === ($this->bitwiseAnd($subLong, $mask)));
    }

    private function ipToLong(string $packed): string
    {
        $hex = bin2hex($packed);
        $dec = '0';
        for ($i = 0, $len = strlen($hex); $i < $len; $i++) {
            $dec = bcadd(bcmul($dec, '16'), (string) hexdec($hex[$i]));
        }
        return $dec;
    }

    private function bitwiseAnd(string $a, string $b): string
    {
        // Simple bitwise AND via string manipulation for arbitrary precision
        $binA = $this->decToBin($a, 128);
        $binB = $this->decToBin($b, 128);
        $result = '';
        for ($i = 0; $i < 128; $i++) {
            $result .= ($binA[$i] === '1' && $binB[$i] === '1') ? '1' : '0';
        }
        return $this->binToDec($result);
    }

    private function decToBin(string $dec, int $pad): string
    {
        $bin = '';
        while (bccomp($dec, '0') > 0) {
            $bin = bcmod($dec, '2') . $bin;
            $dec = bcdiv($dec, '2', 0);
        }
        return str_pad($bin, $pad, '0', STR_PAD_LEFT);
    }

    private function binToDec(string $bin): string
    {
        $dec = '0';
        for ($i = 0, $len = strlen($bin); $i < $len; $i++) {
            $dec = bcadd(bcmul($dec, '2'), $bin[$i]);
        }
        return $dec;
    }
}
