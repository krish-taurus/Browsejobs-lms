<?php

declare(strict_types=1);

namespace App\Actions\Funnel;

use App\Actions\LiveClasses\ScheduleBatchSeries;
use App\Enums\BatchMemberStatus;
use App\Enums\BatchType;
use App\Models\Batch;
use App\Support\Audit\AuditLogger;
use Carbon\CarbonImmutable;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

/**
 * Funnel stage 2: a cohort's masterclass day is over, so the SAME batch becomes
 * its 7-day bootcamp.
 *
 * The cohort keeps one batch number for its whole life — masterclass →
 * bootcamp → paid — so DE-202608-01 is a cohort, not a stage. Nothing is
 * copied between batches: members, attendance and history stay attached to the
 * one row, and only `type` (plus the end date) moves on.
 *
 * `starts_on` deliberately stays on the masterclass date: it is the cohort's
 * start, and the masterclass session keeps its own date in `live_sessions`.
 *
 * Idempotent: changing the type removes the batch from the query below.
 */
final readonly class RolloverMasterclassesToBootcamps
{
    public function __construct(
        private ScheduleBatchSeries $scheduleSeries,
        private SendBatchCredentials $credentials,
        private AuditLogger $audit,
    ) {}

    /**
     * @return array{rolled: int, enrolled: int}
     */
    public function handle(): array
    {
        $rolled = 0;
        $enrolled = 0;

        $masterclasses = Batch::query()
            ->where('type', BatchType::Masterclass->value)
            ->where('status', 'active')
            ->whereNotNull('starts_on')
            ->whereDate('starts_on', '<', Carbon::today()->toDateString())
            ->with('course')
            ->get();

        foreach ($masterclasses as $masterclass) {
            $result = $this->rollOne($masterclass);
            $rolled += $result['rolled'];
            $enrolled += $result['enrolled'];
        }

        return ['rolled' => $rolled, 'enrolled' => $enrolled];
    }

    /**
     * Turn one masterclass batch into its bootcamp, whatever the date.
     *
     * The sweep only picks up cohorts whose masterclass day has passed; this is
     * the same work for a single named batch, so the CRM can move a cohort on
     * early instead of waiting for the daily run.
     *
     * @return array{rolled: int, enrolled: int}
     */
    public function rollOne(Batch $batch): array
    {
        if ($batch->type !== BatchType::Masterclass) {
            throw ValidationException::withMessages([
                'batch' => 'Only a masterclass batch can move to bootcamp.',
            ]);
        }

        $start = Carbon::parse($batch->starts_on ?? Carbon::today());

        // The bootcamp opens the Monday after the masterclass (Sat or Sun) and
        // runs 7 days inclusive.
        $bootcampStart = $start->copy()->next(Carbon::MONDAY);
        $bootcampEnd = $bootcampStart->copy()->addDays(6);

        $batch->forceFill([
            'type' => BatchType::Bootcamp->value,
            'ends_on' => $bootcampEnd->toDateString(),
        ])->save();

        $occupying = array_map(fn (BatchMemberStatus $s) => $s->value, BatchMemberStatus::occupying());
        $members = $batch->members()->whereIn('status', $occupying)->with('student')->get();

        $enrolled = 0;

        foreach ($members as $member) {
            if ($member->student === null) {
                continue;
            }

            // Nobody is re-enrolled — they were already seated for the
            // masterclass — but they are told the bootcamp is starting.
            $this->credentials->handle($member->student, $batch);
            $enrolled++;
        }

        $this->scheduleSeries->handle(
            $batch,
            weekdays: [1, 2, 3, 4, 5, 6, 7],
            time: (string) config('funnel.class_time', '19:00'),
            durationMinutes: (int) config('funnel.class_duration_minutes', 90),
            count: 7,
            startDate: CarbonImmutable::parse($bootcampStart->toDateString()),
            titlePrefix: 'Bootcamp Day',
            mapTopics: true,
        );

        $this->audit->log(
            action: 'batch.stage_advanced',
            target: $batch,
            metadata: ['from' => 'masterclass', 'to' => 'bootcamp', 'members' => $enrolled],
        );

        return ['rolled' => 1, 'enrolled' => $enrolled];
    }
}
