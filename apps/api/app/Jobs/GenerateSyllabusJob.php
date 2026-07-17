<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Actions\Content\GenerateSyllabus;
use App\Models\Syllabus;
use App\Models\Tenant;
use App\Models\User;
use App\Services\AI\AiBudgetExceeded;
use App\Support\Tenancy\TenantContext;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

/**
 * Queued AI syllabus generation (PRD §6.2; every AI call is a queued job per
 * CLAUDE.md). Bills the triggering staff user. A budget/parse failure leaves the
 * syllabus untouched (no crash-loop) — the trainer retries. Regeneration always
 * produces a fresh draft, superseding an approved one only after re-approval; the
 * previously rendered artifact keeps serving in the meantime.
 */
final class GenerateSyllabusJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(
        public readonly int $syllabusId,
        public readonly int $actorUserId,
    ) {}

    public function handle(GenerateSyllabus $generate): void
    {
        $syllabus = Syllabus::query()->withoutGlobalScopes()->find($this->syllabusId);
        if ($syllabus === null) {
            return;
        }

        $tenant = Tenant::query()->find($syllabus->tenant_id);
        $actor = User::query()->withoutGlobalScopes()->find($this->actorUserId);
        if ($tenant === null || $actor === null) {
            return;
        }

        app(TenantContext::class)->run($tenant, function () use ($syllabus, $actor, $generate): void {
            try {
                $generate->handle($syllabus, $actor);
            } catch (AiBudgetExceeded|Throwable) {
                // Leave the syllabus untouched; the trainer retries. Do not fail the loop.
            }
        });
    }
}
