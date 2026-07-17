<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\CurriculumChanged;
use App\Jobs\GenerateSyllabusJob;
use App\Models\Course;
use App\Models\Syllabus;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Syllabus\SyllabusHasher;
use App\Support\Tenancy\TenantContext;
use Illuminate\Contracts\Queue\ShouldQueue;

/**
 * Regenerates a course's syllabus draft when its curriculum changes (PRD §6.2).
 * Opt-in: only courses that already have a syllabus row regenerate. Idempotent — a
 * no-op when the curriculum hash is unchanged, so unrelated edits don't churn.
 *
 * The previously rendered (approved) artifact keeps serving; only the working draft
 * is refreshed, and the syllabus is flagged stale so a trainer re-approves to publish
 * the update (running batches unaffected — PRD §6.21). Regeneration is billed to a
 * resolved staff user in the tenant.
 */
final class RegenerateSyllabusOnCurriculumChange implements ShouldQueue
{
    public function __construct(private readonly SyllabusHasher $hasher) {}

    public function handle(CurriculumChanged $event): void
    {
        $tenant = Tenant::query()->find($event->tenantId);
        if ($tenant === null) {
            return;
        }

        app(TenantContext::class)->run($tenant, function () use ($event): void {
            $course = Course::query()->find($event->courseId);
            if ($course === null) {
                return;
            }

            $syllabus = Syllabus::query()->where('course_id', $course->id)->first();
            if ($syllabus === null) {
                return; // no syllabus for this course — nothing to regenerate
            }

            if ($this->hasher->hash($course) === $syllabus->curriculum_hash) {
                return; // curriculum unchanged relative to the current draft — no churn
            }

            // A previously published syllabus is now behind the curriculum.
            if ($syllabus->isRendered()) {
                $syllabus->update(['is_stale' => true, 'version' => $syllabus->version + 1]);
            }

            $actor = $this->billingActor($event->tenantId);
            if ($actor === null) {
                return; // can't bill a regeneration; the stale flag still nudges a trainer
            }

            GenerateSyllabusJob::dispatch($syllabus->id, $actor->id);
        });
    }

    /** A staff user in the tenant to bill the regeneration to (never a student). */
    private function billingActor(int $tenantId): ?User
    {
        return User::query()
            ->withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('user_type', 'staff')
            ->orderBy('id')
            ->first();
    }
}
