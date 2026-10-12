<?php

declare(strict_types=1);

namespace App\Support\Interviews\Bots;

use Carbon\CarbonInterface;

/**
 * A bot that joins a video meeting as a participant and runs a web page
 * inside the call: the meeting's audio is the page's microphone, and the
 * page's sound and picture are what the others in the call hear and see.
 * That is how the AI interviewer takes an L1/L2 interview on Zoom.
 *
 * Every implementation is called only from queued jobs. Tests bind a fake.
 */
interface MeetingBotClient
{
    /** False when no provider key is configured: interviews then stay in the BrowseJobs room. */
    public function enabled(): bool;

    /**
     * Send a bot to the meeting at $joinAt, showing $pageUrl.
     *
     * @param  array<string, string>  $metadata
     * @return string the provider's bot id
     */
    public function schedule(string $meetingUrl, CarbonInterface $joinAt, string $pageUrl, array $metadata = []): string;

    /** Call off a bot that has not joined yet (a rebooked slot). */
    public function cancel(string $botId): void;

    /** Make a bot in a call leave it now (the interview is over). */
    public function leave(string $botId): void;

    /** The finished call's recording (mp4) download URL, or null while it is still being processed. */
    public function recordingUrl(string $botId): ?string;
}
