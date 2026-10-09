<?php

declare(strict_types=1);

namespace App\Support\AI;

use RuntimeException;

/**
 * A caller asked for a specific LLM provider that has no usable config (no API
 * key, or no base URL for `custom`). Distinct from a transport failure so the
 * caller can tell "add a key" apart from "the vendor said no".
 */
final class ProviderNotConfigured extends RuntimeException
{
    public static function for(string $provider): self
    {
        return new self("AI provider [{$provider}] is not configured — add its API key first.");
    }
}
