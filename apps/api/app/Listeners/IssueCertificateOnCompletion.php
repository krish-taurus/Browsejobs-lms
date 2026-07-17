<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Actions\Certificates\IssueCertificate;
use App\Events\CourseCompleted;
use App\Support\Tenancy\TenantContext;
use Illuminate\Contracts\Queue\ShouldQueue;

/**
 * Auto-issues a course-completion certificate (PRD §6.5). Queued and auto-discovered.
 * Idempotency lives in the action, so a re-fired event never duplicates a cert.
 */
final class IssueCertificateOnCompletion implements ShouldQueue
{
    public function __construct(private readonly IssueCertificate $issue) {}

    public function handle(CourseCompleted $event): void
    {
        $tenant = $event->user->tenant;
        if ($tenant === null) {
            return;
        }

        app(TenantContext::class)->run($tenant, fn () => $this->issue->handle($event->user, $event->course));
    }
}
