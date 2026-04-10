<?php
declare(strict_types=1);

namespace MonkeysLegion\Http\Negotiation;

/**
 * MonkeysLegion Framework — HTTP Package
 *
 * Parses HTTP Accept headers into an ordered list of MIME types.
 *
 * @copyright 2026 MonkeysCloud Team
 * @license   MIT
 */
final class Accept
{
    /**
     * Parse an Accept header into MIME types ordered by quality factor.
     *
     * @return list<string> MIME types ordered by decreasing q value.
     */
    public static function parse(string $header): array
    {
        if ($header === '') {
            return ['*/*'];
        }

        $parts = array_map('trim', explode(',', $header));
        $items = [];

        foreach ($parts as $p) {
            if (!str_contains($p, ';')) {
                $items[$p] = 1.0;
                continue;
            }
            [$mime, $params] = array_map('trim', explode(';', $p, 2));
            if (preg_match('/q=([0-9.]+)/', $params, $m)) {
                $items[$mime] = (float) $m[1];
            } else {
                $items[$mime] = 1.0;
            }
        }

        arsort($items, SORT_NUMERIC);
        return array_keys($items);
    }
}