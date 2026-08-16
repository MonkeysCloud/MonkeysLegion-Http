<?php

declare(strict_types=1);

namespace MonkeysLegion\Http\Tests\Unit\Support;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;

final class HelpersTest extends TestCase
{
    #[Test]
    public function response_creates_text_response(): void
    {
        $response = \response('Hello', 201, ['X-Test' => '1']);

        $this->assertSame(201, $response->getStatusCode());
        $this->assertSame('Hello', (string) $response->getBody());
        $this->assertSame('1', $response->getHeaderLine('X-Test'));
    }

    #[Test]
    public function json_creates_json_response(): void
    {
        $response = \json(['a' => 1]);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('application/json', $response->getHeaderLine('Content-Type'));
        $data = \json_decode((string) $response->getBody(), true);
        $this->assertIsArray($data);
        $this->assertSame(1, $data['a']);
    }

    #[Test]
    public function json_success_wraps_envelope(): void
    {
        $response = \jsonSuccess(['id' => 7], 'Created', 201);

        $this->assertSame(201, $response->getStatusCode());
        $data = \json_decode((string) $response->getBody(), true);
        $this->assertIsArray($data);
        $this->assertSame('success', $data['status']);
        $this->assertSame('Created', $data['message']);
        $this->assertIsArray($data['data']);
        $this->assertSame(7, $data['data']['id']);
    }

    #[Test]
    public function json_error_returns_error_payload(): void
    {
        $response = \jsonError('Nope', 422);

        $this->assertSame(422, $response->getStatusCode());
        $data = \json_decode((string) $response->getBody(), true);
        $this->assertIsArray($data);
        $this->assertSame('error', $data['status']);
        $this->assertSame('Nope', $data['message']);
    }

    #[Test]
    public function status_helpers_return_expected_codes(): void
    {
        $this->assertSame(200, \ok()->getStatusCode());
        $this->assertSame(201, \created()->getStatusCode());
        $this->assertSame(202, \accepted()->getStatusCode());
        $this->assertSame(204, \noContent()->getStatusCode());
        $this->assertSame(400, \badRequest()->getStatusCode());
        $this->assertSame(401, \unauthorized()->getStatusCode());
        $this->assertSame(403, \forbidden()->getStatusCode());
        $this->assertSame(404, \notFound()->getStatusCode());
        $this->assertSame(405, \methodNotAllowed()->getStatusCode());
        $this->assertSame(409, \conflict()->getStatusCode());
        $this->assertSame(422, \unprocessableEntity()->getStatusCode());
        $this->assertSame(500, \internalServerError()->getStatusCode());
        $this->assertSame(501, \notImplemented()->getStatusCode());
        $this->assertSame(503, \serviceUnavailable()->getStatusCode());
    }

    #[Test]
    public function redirect_helpers_set_location(): void
    {
        $this->assertRedirect(\redirect('/dashboard'), '/dashboard', 302);
        $this->assertRedirect(\permanentRedirect('/gone'), '/gone', 301);
    }

    #[Test]
    public function html_helper_sets_content_type(): void
    {
        $response = \html('<h1>Hi</h1>');

        $this->assertSame(200, $response->getStatusCode());
        $this->assertStringContainsString('text/html', $response->getHeaderLine('Content-Type'));
        $this->assertSame('<h1>Hi</h1>', (string) $response->getBody());
    }

    private function assertRedirect(ResponseInterface $response, string $location, int $status): void
    {
        $this->assertSame($status, $response->getStatusCode());
        $this->assertSame($location, $response->getHeaderLine('Location'));
    }
}
