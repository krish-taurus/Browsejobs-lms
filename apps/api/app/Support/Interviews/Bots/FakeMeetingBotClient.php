<?php

declare(strict_types=1);

namespace App\Support\Interviews\Bots;

use Carbon\CarbonInterface;

/** Records what would have been sent to the bot provider. */
final class FakeMeetingBotClient implements MeetingBotClient
{
    /** @var list<array{id: string, meeting_url: string, join_at: CarbonInterface, page_url: string, metadata: array<string, string>}> */
    public array $scheduled = [];

    /** @var list<string> */
    public array $cancelled = [];

    /** @var list<string> */
    public array $left = [];

    /** @var array<string, string> bot id => recording URL */
    public array $recordings = [];

    private int $sequence = 0;

    public function __construct(public bool $on = true) {}

    public function enabled(): bool
    {
        return $this->on;
    }

    public function schedule(string $meetingUrl, CarbonInterface $joinAt, string $pageUrl, array $metadata = []): string
    {
        $id = 'bot-'.(++$this->sequence);
        $this->scheduled[] = ['id' => $id, 'meeting_url' => $meetingUrl, 'join_at' => $joinAt, 'page_url' => $pageUrl, 'metadata' => $metadata];

        return $id;
    }

    public function cancel(string $botId): void
    {
        $this->cancelled[] = $botId;
    }

    public function leave(string $botId): void
    {
        $this->left[] = $botId;
    }

    public function recordingUrl(string $botId): ?string
    {
        return $this->recordings[$botId] ?? null;
    }
}
