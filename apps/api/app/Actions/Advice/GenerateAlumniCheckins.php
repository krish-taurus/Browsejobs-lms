<?php

declare(strict_types=1);

namespace App\Actions\Advice;

use App\Models\AlumniCheckin;
use App\Models\JobApplication;
use App\Support\Messaging\Messenger;
use Illuminate\Support\Facades\DB;

/**
 * Schedules due alumni check-ins (PRD §6.21). For every placement, once it turns
 * 6 and 12 months old, create the check-in (idempotent via the unique key) and
 * best-effort notify the alumnus to respond in-portal. Runs inside a tenant
 * context set by the caller.
 */
final readonly class GenerateAlumniCheckins
{
    public function __construct(private Messenger $messenger) {}

    /** @return int number of check-ins created */
    public function handle(): int
    {
        $created = 0;

        foreach (AlumniCheckin::MILESTONE_MONTHS as $milestone => $months) {
            $due = JobApplication::query()
                ->where('status', JobApplication::STATUS_PLACED)
                ->whereNotNull('placed_at')
                ->where('placed_at', '<=', now()->subMonths($months))
                ->with('student:id,tenant_id,name')
                ->get();

            foreach ($due as $application) {
                if ($application->student === null) {
                    continue;
                }

                $checkin = DB::transaction(function () use ($application, $milestone, $months): ?AlumniCheckin {
                    $exists = AlumniCheckin::query()
                        ->where('user_id', $application->user_id)
                        ->where('job_application_id', $application->id)
                        ->where('milestone', $milestone)
                        ->lockForUpdate()
                        ->exists();

                    if ($exists) {
                        return null;
                    }

                    return AlumniCheckin::query()->create([
                        'tenant_id' => $application->tenant_id,
                        'user_id' => $application->user_id,
                        'job_application_id' => $application->id,
                        'milestone' => $milestone,
                        'status' => AlumniCheckin::STATUS_SCHEDULED,
                        'scheduled_for' => $application->placed_at->copy()->addMonths($months)->toDateString(),
                    ]);
                });

                if ($checkin === null) {
                    continue;
                }

                $created++;
                $this->notify($application, $months);
            }
        }

        return $created;
    }

    private function notify(JobApplication $application, int $months): void
    {
        if ($application->student === null) {
            return;
        }

        // Best-effort: no template configured → Messenger returns null, no throw.
        $this->messenger->send($application->student, 'alumni.checkin', [
            'name' => (string) $application->student->name,
            'months' => (string) $months,
        ]);
    }
}
