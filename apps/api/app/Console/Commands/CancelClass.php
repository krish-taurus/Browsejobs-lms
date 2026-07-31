<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\LiveClasses\CancelLiveSession;
use App\Console\Commands\Concerns\ResolvesCrmTargets;
use App\Enums\LiveSessionStatus;
use Illuminate\Console\Command;

/**
 * Cancels a single class of any batch — Zoom meeting deleted, students and
 * staff notified, reminder ladder voided. Driven by the CRM's Live Batches
 * page (masterclass:cancel cancels the whole masterclass batch instead).
 */
final class CancelClass extends Command
{
    use ResolvesCrmTargets;

    protected $signature = 'class:cancel
        {session : Live session id}
        {--reason=Cancelled by the academic team : Shown to students in the notification}';

    protected $description = 'Cancel a single class (Zoom removed, batch notified, reminders voided)';

    public function handle(CancelLiveSession $cancel): int
    {
        $session = $this->findSession((string) $this->argument('session'));

        if ($session === null) {
            $this->error("Session '{$this->argument('session')}' not found.");

            return self::FAILURE;
        }

        if ($session->status === LiveSessionStatus::Cancelled) {
            $this->warn('This class is already cancelled.');

            return self::SUCCESS;
        }

        return $this->runForTenant($session->tenant_id, function () use ($session, $cancel): int {
            $cancel->handle($session, (string) $this->option('reason'));

            $this->info("Class #{$session->id} cancelled — batch notified.");

            return self::SUCCESS;
        });
    }
}
