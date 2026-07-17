<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Actions\Cv\GenerateCv;
use App\Enums\EntitlementFeature;
use App\Events\CourseCompleted;
use App\Models\CreditTransaction;
use App\Models\CvDocument;
use App\Models\InAppNotification;
use App\Support\Entitlements\EntitlementService;
use App\Support\Tenancy\TenantContext;
use Illuminate\Contracts\Queue\ShouldQueue;

/**
 * The moment a course completes, the student's most comprehensive CV builds
 * itself (PRD §6.7) — credit-free, with a celebration card — and their
 * included free generations (monetization cv_free_grants) land in the
 * wallet. Queued, auto-discovered, idempotent per user+course.
 */
final class GenerateCvOnCompletion implements ShouldQueue
{
    public function __construct(
        private readonly GenerateCv $generate,
        private readonly EntitlementService $entitlements,
    ) {}

    public function handle(CourseCompleted $event): void
    {
        $student = $event->user;
        $tenant = $student->tenant;
        if ($tenant === null) {
            return;
        }

        app(TenantContext::class)->run($tenant, function () use ($student, $event): void {
            $already = CvDocument::query()
                ->where('user_id', $student->id)
                ->where('course_id', $event->course->id)
                ->where('source', CvDocument::SOURCE_AUTO)
                ->exists();

            if ($already) {
                return;
            }

            $cv = $this->generate->handle($student, CvDocument::SOURCE_AUTO, courseId: $event->course->id);

            // Included free generations, once per student (ledger-checked).
            $granted = CreditTransaction::query()
                ->where('user_id', $student->id)
                ->where('feature', EntitlementFeature::Cv->value)
                ->where('reason', 'cv_included')
                ->exists();

            if (! $granted) {
                $free = $this->entitlements->settings()->cv_free_grants;
                if ($free > 0) {
                    $this->entitlements->grantCredits($student, EntitlementFeature::Cv->value, $free, 'cv_included');
                }
            }

            InAppNotification::query()->create([
                'tenant_id' => $student->tenant_id,
                'user_id' => $student->id,
                'title' => "Your CV is ready 🎉 — {$event->course->name} complete",
                'body' => 'We built it from your real record: modules, graded labs, certificates and interview strengths. Review and tailor it on your CV page.',
                'url' => '/cv',
            ]);

            unset($cv);
        });
    }
}
