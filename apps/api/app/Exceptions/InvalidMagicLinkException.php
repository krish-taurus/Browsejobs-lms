<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

/**
 * Thrown when a magic link is unknown, already consumed, or expired.
 */
final class InvalidMagicLinkException extends RuntimeException
{
    public function __construct(string $message = 'This link is invalid or has expired.')
    {
        parent::__construct($message);
    }
}
