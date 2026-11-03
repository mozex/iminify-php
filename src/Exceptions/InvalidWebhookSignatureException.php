<?php

declare(strict_types=1);

namespace Mozex\Iminify\Exceptions;

/**
 * A webhook request whose signature doesn't hold up, so it can't be trusted to come from Iminify.
 */
class InvalidWebhookSignatureException extends IminifyException
{
    public static function missing(): self
    {
        return new self('The webhook request has no usable Iminify-Signature header.');
    }

    public static function mismatch(): self
    {
        return new self('The webhook signature does not match the body and the signing secret.');
    }

    public static function outsideTolerance(int $tolerance): self
    {
        return new self("The webhook signature is more than {$tolerance} seconds from this server's clock, so it may be an old request sent again.");
    }
}
