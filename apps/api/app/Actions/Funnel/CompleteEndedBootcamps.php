<?php

declare(strict_types=1);

namespace App\Actions\Funnel;

use App\Actions\Conversion\CompleteBootcamp;
use App\Actions\LiveClasses\ScheduleBatchSeries;
use App\Enums\BatchType;
use App\Models\Batch;
use App\Models\Topic;
use Carbon\CarbonImmutable;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

/**
 * Weekly funnel stage 3 (runs within a tenant context): every bootcamp whose
 * 7 days are over is completed, which converts each occupying attendee into
 * the linked paid batch as Reserved — from there the fee plan, dunning ladder
 * and payment webhook take over (Enrolled on first instalment). A bootcamp
 * without a linked paid batch is left for an admin. Idempotent: completing
 * flips status so the batch drops out of the query.
 */
final readonly class CompleteEndedBootcamps
{
    public function __construct(
        private CompleteBootcamp $completeBootcamp,
        private ScheduleBatchSeries $scheduleSeries,
    ) {}

    /**
     * @return array{completed: int, converted: int, skipped: int}
     */
    public function handle(): array
    {
        $completed = 0;
        $converted = 0;
        $skipped = 0;

        $bootcamps = Batch::query()
            ->where('type', BatchType::Bootcamp->value)
            ->where('status', 'active')
            ->whereNotNull('ends_on')
            ->whereDate('ends_on', '<', Carbon::today()->toDateString())
            ->get();

        foreach ($bootcamps as $bootcamp) {
            try {
                $summary = $this->completeBootcamp->handle($bootcamp);
                $completed++;
                $converted += $summary['converted'];
            } catch (ValidationException) {
                $skipped++; // no linked paid batch — an admin must link one

                continue;
            }

            $this->schedulePaidBatchClasses($bootcamp);
        }

        return ['completed' => $completed, 'converted' => $converted, 'skipped' => $skipped];
    }

    /**
     * Fully automatic paid-batch calendar: one weekday class per syllabus
     * topic (falling back to a fixed count when the course has no topics yet),
     * starting when the paid batch starts. Idempotent per slot — re-runs and
     * manual additions never double-book.
     */
    private function schedulePaidBatchClasses(Batch $bootcamp): void
    {
        $paid = Batch::query()
            ->where('linked_source_batch_id', $bootcamp->id)
            ->where('type', BatchType::Paid->value)
            ->first();

        if ($paid === null) {
            return;
        }

        $topicCount = Topic::query()
            ->whereHas('module', fn ($q) => $q->where('course_id', $paid->course_id))
            ->count();

        $start = $paid->starts_on
            ? CarbonImmutable::parse($paid->starts_on)
            : CarbonImmutable::parse($bootcamp->ends_on)->addDay();

        $this->scheduleSeries->handle(
            $paid,
            weekdays: (array) config('funnel.paid_class_weekdays', [1, 2, 3, 4, 5]),
            time: (string) config('funnel.class_time', '19:00'),
            durationMinutes: (int) config('funnel.class_duration_minutes', 90),
            count: $topicCount > 0 ? $topicCount : (int) config('funnel.paid_class_fallback_count', 20),
            startDate: $start,
            titlePrefix: 'Class',
            mapTopics: true,
        );
    }
}
