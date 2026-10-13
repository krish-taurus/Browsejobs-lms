<?php

declare(strict_types=1);

namespace App\Support\Employers\Agent;

use App\Models\EmployerAgentAction;

/**
 * Taurus's answer to one thing a recruiter said.
 *
 * `intent` tells the channel what happened: proposed (an action is waiting
 * for YES), done / failed / held (a pending one was answered), answered (a
 * question), post_job (a new role — the channel's own JD flow takes over),
 * or chat.
 */
final readonly class AgentReply
{
    public function __construct(
        public string $intent,
        public string $text,
        public ?EmployerAgentAction $action = null,
    ) {}

    /** @return array{intent: string, reply: string, action: array<string, mixed>|null} */
    public function toApi(): array
    {
        return [
            'intent' => $this->intent,
            'reply' => $this->text,
            'action' => $this->action?->toApi(),
        ];
    }
}
