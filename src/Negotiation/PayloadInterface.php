<?php
declare(strict_types=1);

namespace MonkeysLegion\Http\Negotiation;

/**
 * Controllers **may** return any PSR-7 ResponseInterface.
 * If they return *something else* (array, object, scalar…), the
 * ContentNegotiationMiddleware will serialise that payload.
 */
interface PayloadInterface
{
    /** Convert the domain object into a plain PHP structure (array / scalar) */
    public function toPayload(): mixed;
}