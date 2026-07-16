<?php

declare(strict_types=1);

namespace App\Services\AI;

/**
 * The AI transport (Anthropic Messages API). Called only from the AiGateway (which
 * enforces the budget + logs ai_events). Tests bind {@see FakeAiClient}.
 */
interface AiClient
{
    public function complete(AiMessage $message): AiResult;
}
