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

    /**
     * Optional FIFO queue for multi-turn tests (P4.1 adaptive flows): when
     * non-empty, each call shifts the next reply; when drained, falls back to
     * $reply. Backwards-compatible with every single-reply test.
     *
     * @var list<string>
     */
    public array $replies = [];

    public int $promptTokens = 42;

    public int $completionTokens = 18;

    /**
     * Why the model stopped. Defaults to a clean finish; set to 'length' to
     * reproduce a reply cut off at the token ceiling, which consumers must
     * distinguish from a badly-shaped answer.
     */
    public string $stopReason = 'end_turn';

    public function complete(AiMessage $message): AiResult
    {
        $this->calls[] = $message;

        $text = $this->replies !== [] ? array_shift($this->replies) : $this->reply;

        return new AiResult(
            text: $text,
            promptTokens: $this->promptTokens,
            completionTokens: $this->completionTokens,
            model: $message->model ?? 'claude-sonnet-5',
            stopReason: $this->stopReason,
        );
    }
}
