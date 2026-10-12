<?php

declare(strict_types=1);

namespace App\Support\Interviews\Bots;

use Carbon\CarbonInterface;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

/**
 * Recall.ai meeting bots (https://docs.recall.ai). The bot's camera is a web
 * page ("output media"): Recall grants that page the meeting audio as its
 * microphone and streams the page's audio and video into the call. The call
 * is recorded as one mixed mp4.
 */
final readonly class RecallMeetingBotClient implements MeetingBotClient
{
    /** @param  array{api_key: string, base_url: string, bot_name: string}  $config */
    public function __construct(private array $config) {}

    public function enabled(): bool
    {
        return $this->config['api_key'] !== '';
    }

    public function schedule(string $meetingUrl, CarbonInterface $joinAt, string $pageUrl, array $metadata = []): string
    {
        $response = $this->request()->post('/bot/', [
            'meeting_url' => $meetingUrl,
            'bot_name' => $this->config['bot_name'],
            // At least 10 minutes ahead is a scheduled bot; sooner joins straight away.
            'join_at' => $joinAt->copy()->utc()->toIso8601ZuluString(),
            'output_media' => [
                'camera' => ['kind' => 'webpage', 'config' => ['url' => $pageUrl]],
            ],
            // Output media needs the larger bot to run the page smoothly.
            'variant' => ['zoom' => 'web_4_core'],
            'recording_config' => ['video_mixed_mp4' => (object) []],
            'automatic_leave' => [
                // Nobody came: leave after 15 minutes. Candidate left: leave after 30 seconds.
                'noone_joined_timeout' => 900,
                'everyone_left_timeout' => ['timeout' => 30],
            ],
            'metadata' => (object) $metadata,
        ])->throw()->json();

        return (string) $response['id'];
    }

    public function cancel(string $botId): void
    {
        $this->request()->delete('/bot/'.rawurlencode($botId).'/');
    }

    public function leave(string $botId): void
    {
        $this->request()->post('/bot/'.rawurlencode($botId).'/leave_call/');
    }

    public function recordingUrl(string $botId): ?string
    {
        $bot = $this->request()->get('/bot/'.rawurlencode($botId).'/')->throw()->json();

        foreach ((array) ($bot['recordings'] ?? []) as $recording) {
            $video = $recording['media_shortcuts']['video_mixed'] ?? null;
            $url = $video['data']['download_url'] ?? null;
            if (($video['status']['code'] ?? null) === 'done' && is_string($url) && $url !== '') {
                return $url;
            }
        }

        return null;
    }

    private function request(): PendingRequest
    {
        return Http::baseUrl(rtrim($this->config['base_url'], '/'))
            ->withHeaders(['Authorization' => 'Token '.$this->config['api_key']])
            ->acceptJson()
            ->timeout(20);
    }
}
