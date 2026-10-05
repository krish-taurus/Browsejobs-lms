<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Console\Commands\Concerns\ResolvesCrmTargets;
use App\Enums\LiveSessionStatus;
use App\Models\LiveSession;
use Illuminate\Console\Command;

/**
 * Hands the CRM the Zoom host `start_url` for one class so staff can open the
 * meeting AS HOST instead of landing in "waiting for the host to start".
 *
 * The URL is printed, never stored in the CRM or rendered into a page: a
 * start_url carries host authority, so it is fetched at click time and used
 * immediately. Who may ask is decided by the CRM's own permission; what this
 * command enforces is the state of the class — not cancelled, Zoom ready, and
 * inside the host window (from HOST_LEAD_MINUTES before to HOST_TRAIL_MINUTES
 * after), the same window the LMS trainer board uses.
 */
final class ClassStartUrl extends Command
{
    use ResolvesCrmTargets;

    protected $signature = 'class:start-url {session : Live session id}';

    protected $description = 'Print the Zoom host start URL for a class (CRM "Start as host")';

    public function handle(): int
    {
        $session = $this->findSession((string) $this->argument('session'));

        if ($session === null) {
            $this->error("Session '{$this->argument('session')}' not found.");

            return self::FAILURE;
        }

        return $this->runForTenant($session->tenant_id, function () use ($session): int {
            if (in_array($session->status, [LiveSessionStatus::Cancelled, LiveSessionStatus::Ended], true)) {
                $this->error('This class is not open to start.');

                return self::FAILURE;
            }

            if ($session->zoom_start_url === null) {
                $this->error('The Zoom meeting for this class is not ready yet — check the Zoom license.');

                return self::FAILURE;
            }

            if (! $session->hostWindowOpen()) {
                $this->error(sprintf(
                    'Outside the host window — you can start this class from %d minutes before it begins until %d minutes after it ends.',
                    LiveSession::HOST_LEAD_MINUTES,
                    LiveSession::HOST_TRAIL_MINUTES,
                ));

                return self::FAILURE;
            }

            $this->line($session->zoom_start_url);

            return self::SUCCESS;
        });
    }
}
