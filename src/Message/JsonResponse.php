<?php
declare(strict_types=1);

namespace MonkeysLegion\Http\Message;

/**
 * MonkeysLegion Framework — HTTP Package
 *
 * Convenience JSON response with envelope and pagination support.
 *
 * v2 improvements:
 *  • withEnvelope() — wraps data in { status, data, meta, message }
 *  • withPagination() — adds cursor/offset pagination metadata
 *  • Extends Response for full PSR-7 compliance
 *
 * @copyright 2026 MonkeysCloud Team
 * @license   MIT
 */
final class JsonResponse extends Response
{
    /**
     * @param mixed $data   Any JSON-serializable value.
     * @param int   $status HTTP status code.
     * @param int   $flags  json_encode() flags.
     *
     * @throws \JsonException On encoding failure.
     */
    public function __construct(
        private readonly mixed $data,
        int $status = 200,
        private readonly int $jsonFlags = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
    ) {
        $json = json_encode($data, $this->jsonFlags | JSON_THROW_ON_ERROR);
        parent::__construct(
            Stream::createFromString($json),
            $status,
            ['Content-Type' => 'application/json'],
        );
    }

    /**
     * Wrap the data in a standard API envelope.
     *
     * ```json
     * {
     *   "status": "success",
     *   "message": "Users retrieved",
     *   "data": [...],
     *   "meta": {}
     * }
     * ```
     *
     * @param string|null           $message Optional message.
     * @param array<string, mixed>  $meta    Optional metadata.
     *
     * @throws \JsonException
     */
    public function withEnvelope(
        ?string $message = null,
        array $meta = [],
    ): Response {
        $statusText = $this->getStatusCode() < 400 ? 'success' : 'error';

        $envelope = [
            'status'  => $statusText,
            'message' => $message,
            'data'    => $this->data,
        ];

        if ($meta !== []) {
            $envelope['meta'] = $meta;
        }

        $json = json_encode($envelope, $this->jsonFlags | JSON_THROW_ON_ERROR);
        return new Response(
            Stream::createFromString($json),
            $this->getStatusCode(),
            ['Content-Type' => 'application/json'],
        );
    }

    /**
     * Add pagination metadata to the response.
     *
     * @param int      $total   Total number of items.
     * @param int      $page    Current page number.
     * @param int      $perPage Items per page.
     * @param int|null $lastPage Auto-calculated if null.
     *
     * @throws \JsonException
     */
    public function withPagination(
        int $total,
        int $page,
        int $perPage,
        ?int $lastPage = null,
    ): Response {
        $lastPage ??= (int) ceil($total / max(1, $perPage));

        return $this->withEnvelope(meta: [
            'pagination' => [
                'total'    => $total,
                'page'     => $page,
                'per_page' => $perPage,
                'last_page' => $lastPage,
                'has_more'  => $page < $lastPage,
            ],
        ]);
    }
}