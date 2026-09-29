<?php
declare(strict_types=1);

namespace MonkeysLegion\Http\Support;

/**
 * MonkeysLegion Framework — HTTP Package
 *
 * Generates cryptographically secure per-request nonces for CSP.
 *
 * @copyright 2026 MonkeysCloud Team
 * @license   MIT
 */
final class NonceGenerator
{
    private const int DEFAULT_LENGTH = 32; // bytes → 64 hex chars

    /**
     * Generate a random nonce (hex-encoded).
     *
     * @param int $length Byte length (default 32 → 64 hex chars).
     */
    public function generate(int $length = self::DEFAULT_LENGTH): string
    {
        return bin2hex(random_bytes($length));
    }
}
