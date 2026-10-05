<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Console\Commands\Concerns\ResolvesCrmTargets;
use Illuminate\Console\Command;

/**
 * Points a class at a link staff created themselves — a Zoom Webinar, a
 * larger-capacity meeting, a livestream, anything with an https join page —
 * instead of the auto-created Zoom Meeting. For a class too big for a plain
 * Zoom Meeting's participant cap (the pooled Zoom licenses' own plan limit).
 *
 * Also sets zoom_meeting_id to the 'manual' sentinel. That column is the only
 * thing EnsureZoomMeeting checks before creating a real Zoom meeting — once
 * it is non-null, a later queue retry can never overwrite this link with an
 * auto-created one.
 *
 * No re-notification needed: the LMS's own /classes page reads zoom_join_url
 * fresh at click time. The link was never baked into the WhatsApp/email
 * message itself (see AnnounceClassScheduled), so students see the new one
 * the moment they open the page — even ones already messaged about this class.
 */
final class SetClassJoinUrl extends Command
{
    use ResolvesCrmTargets;

    protected $signature = 'class:set-join-url
        {session : Live session id}
        {url : The link students click to join}
        {--start-url= : A separate host/start link, if the provider has one (defaults to the join link)}';

    protected $description = 'Point a class at a link you created yourself, instead of the auto-created Zoom meeting';

    public function handle(): int
    {
        $session = $this->findSession((string) $this->argument('session'));

        if ($session === null) {
            $this->error("Session '{$this->argument('session')}' not found.");

            return self::FAILURE;
        }

        $url = (string) $this->argument('url');

        if (! str_starts_with($url, 'https://')) {
            $this->error('The join link must start with https://.');

            return self::FAILURE;
        }

        return $this->runForTenant($session->tenant_id, function () use ($session, $url): int {
            $session->update([
                'zoom_join_url' => $url,
                'zoom_start_url' => (string) ($this->option('start-url') ?: $url),
                'zoom_meeting_id' => 'manual',
            ]);

            $this->info("Class #{$session->id} now points to {$url}.");

            return self::SUCCESS;
        });
    }
}
