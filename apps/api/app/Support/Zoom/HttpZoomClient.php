<?php

declare(strict_types=1);

namespace App\Support\Zoom;

use Carbon\CarbonInterface;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Real Zoom Server-to-Server OAuth client. The access token is fetched with the
 * account-credentials grant and cached until shortly before it expires. Only
 * invoked from queued jobs; unit tests use {@see FakeZoomClient}.
 */
final class HttpZoomClient implements ZoomClient
{
    private const TOKEN_CACHE_KEY = 'zoom.s2s.access_token';

    /**
     * @param  array{account_id: string, client_id: string, client_secret: string, base_url: string, oauth_url: string}  $config
     */
    public function __construct(private readonly array $config) {}

    public function createMeeting(
        string $topic,
        CarbonInterface $start,
        int $durationMinutes,
        ?string $hostUserId = null,
        ?bool $autoRecord = null,
    ): ZoomMeeting {
        $settings = ['waiting_room' => true, 'join_before_host' => false];
        if ($autoRecord !== null) {
            $settings['auto_recording'] = $autoRecord ? 'cloud' : 'none';
        }

        // Host under the allocated license's Zoom user, else the account's own user.
        $host = $hostUserId !== null && $hostUserId !== '' ? rawurlencode($hostUserId) : 'me';

        $response = $this->request()->post($this->config['base_url']."/users/{$host}/meetings", [
            'topic' => $topic,
            'type' => 2, // scheduled
            'start_time' => $start->toIso8601ZuluString(),
            'duration' => $durationMinutes,
            'settings' => $settings,
        ])->throw()->json();

        return new ZoomMeeting(
            id: (string) $response['id'],
            joinUrl: (string) $response['join_url'],
            startUrl: (string) $response['start_url'],
        );
    }

    public function updateMeeting(string $meetingId, CarbonInterface $start, int $durationMinutes): void
    {
        $this->request()->patch($this->config['base_url']."/meetings/{$meetingId}", [
            'start_time' => $start->toIso8601ZuluString(),
            'duration' => $durationMinutes,
        ])->throw();
    }

    public function deleteMeeting(string $meetingId): void
    {
        $this->request()->delete($this->config['base_url']."/meetings/{$meetingId}")->throw();
    }

    public function downloadRecording(string $downloadUrl): string
    {
        return $this->request()->get($downloadUrl)->throw()->body();
    }

    private function request(): PendingRequest
    {
        return Http::withToken($this->accessToken())->acceptJson();
    }

    private function accessToken(): string
    {
        $cached = Cache::get(self::TOKEN_CACHE_KEY);
        if (is_string($cached)) {
            return $cached;
        }

        $response = Http::asForm()
            ->withBasicAuth($this->config['client_id'], $this->config['client_secret'])
            ->post($this->config['oauth_url'], [
                'grant_type' => 'account_credentials',
                'account_id' => $this->config['account_id'],
            ])->throw()->json();

        $token = $response['access_token'] ?? null;
        if (! is_string($token)) {
            throw new RuntimeException('Zoom did not return an access token.');
        }

        $ttl = max(60, (int) ($response['expires_in'] ?? 3600) - 60);
        Cache::put(self::TOKEN_CACHE_KEY, $token, $ttl);

        return $token;
    }
}
