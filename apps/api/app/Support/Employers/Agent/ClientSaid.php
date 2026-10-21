<?php

declare(strict_types=1);

namespace App\Support\Employers\Agent;

/**
 * What the client wrote in the message being answered right now, so that every
 * reply on that turn can be given back in their language. One instance per
 * request or queued job (registered as scoped).
 */
final class ClientSaid
{
    private ?string $text = null;

    public function remember(?string $text): void
    {
        $this->text = $text;
    }

    public function text(): ?string
    {
        return $this->text;
    }
}
