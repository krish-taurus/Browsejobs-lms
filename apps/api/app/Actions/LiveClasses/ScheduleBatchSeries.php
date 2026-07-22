<?php

declare(strict_types=1);

namespace App\Actions\LiveClasses;

use App\Enums\LiveSessionStatus;
use App\Models\Batch;
use App\Models\LiveSession;
use App\Models\Topic;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Schedules a batch's whole class calendar in one pass (PRD §6.3 daily/alt-day
 * cohorts). Walks the calendar from a start date, and on every weekday in the
 * chosen pattern creates one live class at a fixed time — delegating each to
 * {@see ScheduleLiveSession} so every class still gets its own Zoom meeting and
 * reminder ladder. Optionally titles the classes from the course syllabus in
 * order, turning "schedule the batch" into "schedule the syllabus, one topic a day".
 *
 * Idempotent per slot: a slot already holding a live (non-cancelled) session for
 * the batch is skipped, so re-running to extend a calendar never double-books.
 */
final readonly class ScheduleBatchSeries
{
    /** Never scan more than a year of calendar looking for pattern days. */
    private const MAX_SCAN_DAYS = 366;

    public function __construct(private ScheduleLiveSession $schedule) {}

    /**
     * @param  list<int>  $weekdays  ISO weekdays that hold class (1 = Mon … 7 = Sun)
     * @param  array<int, string>  $weekdayTimes  optional per-weekday "HH:MM" overriding $time for that day
     * @return list<LiveSession> the sessions created (skipped slots excluded)
     */
    public function handle(
        Batch $batch,
        array $weekdays,
        string $time,
        int $durationMinutes,
        int $count,
        CarbonImmutable $startDate,
        ?string $titlePrefix = null,
        bool $mapTopics = false,
        bool $record = true,
        array $weekdayTimes = [],
    ): array {
        $weekdays = array_values(array_unique(array_filter($weekdays, fn ($d) => $d >= 1 && $d <= 7)));
        if ($weekdays === [] || $count < 1) {
            return [];
        }

        $topics = $mapTopics ? $this->syllabusTopics($batch) : collect();
        $prefix = trim((string) ($titlePrefix ?? 'Class')) ?: 'Class';

        $created = [];
        $cursor = $startDate->startOfDay();
        $scanned = 0;

        while (count($created) < $count && $scanned < self::MAX_SCAN_DAYS) {
            if (in_array($cursor->isoWeekday(), $weekdays, true)) {
                // A per-day time (e.g. Sat mornings, Wed evenings) overrides the default.
                $dayTime = $weekdayTimes[$cursor->isoWeekday()] ?? $time;
                $start = $cursor->setTimeFromTimeString($dayTime);

                if ($start->isFuture() && ! $this->slotTaken($batch, $start)) {
                    $seq = count($created) + 1;
                    /** @var Topic|null $topic */
                    $topic = $topics->get($seq - 1);
                    $title = $topic?->name ?? "{$prefix} {$seq}";

                    $created[] = $this->schedule->handle(
                        $batch,
                        $title,
                        $start,
                        $start->addMinutes($durationMinutes),
                        $topic,
                        $record,
                    );
                }
            }

            $cursor = $cursor->addDay();
            $scanned++;
        }

        return $created;
    }

    /** Course topics in syllabus order (module position, then topic position). */
    private function syllabusTopics(Batch $batch): Collection
    {
        return Topic::query()
            ->whereHas('module', fn ($q) => $q->where('course_id', $batch->course_id))
            ->join('modules', 'modules.id', '=', 'topics.module_id')
            ->orderBy('modules.position')
            ->orderBy('topics.position')
            ->orderBy('topics.id')
            ->select('topics.*')
            ->get();
    }

    /** True if the batch already has a live (non-cancelled) session at this exact start. */
    private function slotTaken(Batch $batch, CarbonImmutable $start): bool
    {
        return LiveSession::query()
            ->where('batch_id', $batch->id)
            ->where('scheduled_start', $start)
            ->where('status', '!=', LiveSessionStatus::Cancelled->value)
            ->exists();
    }
}
