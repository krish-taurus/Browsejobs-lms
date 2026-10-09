<?php

declare(strict_types=1);

namespace App\Actions\Taurus;

use RuntimeException;

/** No LLM has a key, so the Taurus brain has nothing to answer with. */
final class BrainNotConfigured extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('Add an LLM key in Taurus → Brain & voice.');
    }
}
