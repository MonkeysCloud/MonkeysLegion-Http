<?php

declare(strict_types=1);

namespace MonkeysLegion\Http\Tests\Unit\Message;

use MonkeysLegion\Http\Message\JsonResponse;
use MonkeysLegion\Http\Message\Response;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class JsonResponseTest extends TestCase
{
    #[Test]
    public function encodes_data_as_json_with_default_flags(): void
    {
        $response = new JsonResponse(['id' => 1, 'name' => 'José']);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('application/json', $response->getHeaderLine('Content-Type'));
        // JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES are the defaults
        $this->assertSame('{"id":1,"name":"José"}', (string) $response->getBody());
    }

    #[Test]
    public function encodes_utf8_without_escaping(): void
    {
        $response = new JsonResponse(['msg' => 'héllo / wörld']);

        $this->assertSame('{"msg":"héllo / wörld"}', (string) $response->getBody());
    }

    #[Test]
    public function respects_custom_json_flags(): void
    {
        $response = new JsonResponse(['msg' => 'héllo'], jsonFlags: 0);

        $this->assertSame('{"msg":"h\\u00e9llo"}', (string) $response->getBody());
    }

    #[Test]
    public function throws_json_exception_on_encoding_failure(): void
    {
        $this->expectException(\JsonException::class);
        new JsonResponse(\NAN);
    }

    #[Test]
    public function envelope_marks_success_below_400(): void
    {
        $response = new JsonResponse(['id' => 7], status: 201)->withEnvelope(message: 'Created');

        $this->assertSame(201, $response->getStatusCode());
        $data = $this->decode($response);
        $this->assertSame('success', $data['status']);
        $this->assertSame('Created', $data['message']);
        $this->assertSame(['id' => 7], $data['data']);
    }

    #[Test]
    public function envelope_marks_error_at_or_above_400(): void
    {
        $response = new JsonResponse(['error' => 'nope'], status: 404)->withEnvelope(message: 'Not found');

        $this->assertSame(404, $response->getStatusCode());
        $data = $this->decode($response);
        $this->assertSame('error', $data['status']);
        $this->assertSame('Not found', $data['message']);
    }

    #[Test]
    public function envelope_includes_meta_only_when_provided(): void
    {
        $plain = new JsonResponse(['id' => 1])->withEnvelope();
        $plainData = $this->decode($plain);
        $this->assertArrayNotHasKey('meta', $plainData);

        $withMeta = new JsonResponse(['id' => 1])->withEnvelope(meta: ['trace' => 'abc']);
        $withMetaData = $this->decode($withMeta);
        $this->assertSame(['trace' => 'abc'], $withMetaData['meta']);
    }

    #[Test]
    public function pagination_calculates_last_page_with_ceil(): void
    {
        // 10 items, 3 per page → 4 pages (ceil)
        $response = new JsonResponse([])->withPagination(total: 10, page: 1, perPage: 3);

        $pagination = $this->pagination($response);
        $this->assertSame(10, $pagination['total']);
        $this->assertSame(1, $pagination['page']);
        $this->assertSame(3, $pagination['per_page']);
        $this->assertSame(4, $pagination['last_page']);
        $this->assertTrue($pagination['has_more']);
    }

    #[Test]
    public function pagination_accepts_explicit_last_page(): void
    {
        $response = new JsonResponse([])->withPagination(total: 10, page: 3, perPage: 3, lastPage: 5);

        $pagination = $this->pagination($response);
        $this->assertSame(5, $pagination['last_page']);
        $this->assertTrue($pagination['has_more']);
    }

    #[Test]
    public function pagination_has_more_is_false_on_last_page(): void
    {
        $response = new JsonResponse([])->withPagination(total: 6, page: 2, perPage: 3);

        $pagination = $this->pagination($response);
        $this->assertSame(2, $pagination['last_page']);
        $this->assertFalse($pagination['has_more']);
    }

    #[Test]
    public function pagination_guards_against_zero_per_page(): void
    {
        // perPage 0 would divide by zero — max(1, perPage) must kick in
        $response = new JsonResponse([])->withPagination(total: 5, page: 1, perPage: 0);

        $pagination = $this->pagination($response);
        $this->assertSame(5, $pagination['last_page']);
        $this->assertTrue($pagination['has_more']);
    }

    /**
     * @return array<string, mixed>
     */
    private function decode(Response $response): array
    {
        $data = \json_decode((string) $response->getBody(), true);
        if (!\is_array($data)) {
            throw new \RuntimeException('Expected a JSON object response.');
        }
        /** @var array<string, mixed> $data */
        return $data;
    }

    /**
     * @return array<string, mixed>
     */
    private function pagination(Response $response): array
    {
        $data = $this->decode($response);
        $meta = $data['meta'] ?? null;
        if (!\is_array($meta)) {
            throw new \RuntimeException('Response is missing meta metadata.');
        }
        $pagination = $meta['pagination'] ?? null;
        if (!\is_array($pagination)) {
            throw new \RuntimeException('Response is missing pagination metadata.');
        }
        /** @var array<string, mixed> $pagination */
        return $pagination;
    }
}
