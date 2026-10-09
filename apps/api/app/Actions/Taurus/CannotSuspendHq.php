<?php

declare(strict_types=1);

namespace App\Actions\Taurus;

use RuntimeException;

/** The owner workspace (Taurus HQ) cannot be suspended. */
final class CannotSuspendHq extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('Taurus HQ cannot be suspended.');
    }
}
