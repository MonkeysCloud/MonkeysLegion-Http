<?php
declare(strict_types=1);

namespace MonkeysLegion\Http\Negotiation;

/**
 * MonkeysLegion Framework — HTTP Package
 *
 * Domain objects implement this to provide a serializable payload.
 *
 * Controllers may return any PSR-7 ResponseInterface. If they return
 * something else (array, object, scalar), the ContentNegotiationMiddleware
 * will serialize the payload using this interface.
 *
 * @copyright 2026 MonkeysCloud Team
 * @license   MIT
 */
interface PayloadInterface
{
    /**
     * Convert the domain object into a plain PHP structure.
     */
    public function toPayload(): mixed;
}