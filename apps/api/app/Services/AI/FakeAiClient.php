<?php

declare(strict_types=1);

namespace App\Services\AI;

/**
 * In-memory AI transport for tests/dev without a real key. Records calls and
 * returns a deterministic reply + token counts.
 */
final class FakeAiClient implements AiClient
{
    /** @var list<AiMessage> */
    public array $calls = [];

    public string $reply = 'This is a deterministic test reply.';

    public int $promptTokens = 42;

    public int $completionTokens = 18;

    public function complete(AiMessage $message): AiResult
    {
        $this->calls[] = $message;

        return new AiResult(
            text: $this->reply,
            promptTokens: $this->promptTokens,
            completionTokens: $this->completionTokens,
            model: $message->model ?? 'claude-sonnet-5',
        );
    }
}
