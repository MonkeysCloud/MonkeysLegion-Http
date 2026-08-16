<?php

declare(strict_types=1);

namespace MonkeysLegion\Http\Tests\Unit\Negotiation;

use MonkeysLegion\Http\Negotiation\Accept;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class AcceptTest extends TestCase
{
    #[Test]
    public function empty_header_defaults_to_wildcard(): void
    {
        $this->assertSame(['*/*'], Accept::parse(''));
    }

    #[Test]
    public function parses_single_mime(): void
    {
        $this->assertSame(['application/json'], Accept::parse('application/json'));
    }

    #[Test]
    public function orders_by_quality_factor(): void
    {
        $result = Accept::parse('application/json;q=0.8, text/html;q=0.9');

        $this->assertSame(['text/html', 'application/json'], $result);
    }

    #[Test]
    public function defaults_quality_to_one(): void
    {
        $result = Accept::parse('application/xml;charset=utf-8, application/json');

        // Equal quality keeps the original order (stable sort).
        $this->assertSame(['application/xml', 'application/json'], $result);
    }

    #[Test]
    public function ignores_non_quality_parameters(): void
    {
        $this->assertSame(['application/json'], Accept::parse('application/json; charset=utf-8'));
    }
}
