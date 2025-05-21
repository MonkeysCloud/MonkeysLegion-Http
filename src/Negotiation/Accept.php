<?php
declare(strict_types=1);

namespace MonkeysLegion\Http\Negotiation;

final class Accept
{
    /** @return string[] ordered by client preference (q=…) */
    public static function parse(string $header): array
    {
        if ($header === '') {
            return ['*/*'];                           // no header → any
        }

        $parts = array_map('trim', explode(',', $header));
        $items = [];

        foreach ($parts as $p) {
            // split “type/sub;q=0.8”
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

        arsort($items, SORT_NUMERIC);                 // highest q first
        return array_keys($items);
    }
}