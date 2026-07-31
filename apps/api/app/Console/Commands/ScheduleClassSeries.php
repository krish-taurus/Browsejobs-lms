<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\LiveClasses\ScheduleBatchSeries;
use App\Console\Commands\Concerns\ResolvesCrmTargets;
use App\Enums\BatchMemberStatus;
use App\Enums\LiveSessionStatus;
use App\Enums\MessageChannel;
use App\Models\Batch;
use App\Models\LiveSession;
use App\Support\Messaging\Messenger;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;

/**
 * Schedules a batch's whole class calendar in one pass — pick the weekdays and
 * time (e.g. Mon/Wed/Fri 20:00) and every class is created with its own Zoom
 * meeting and reminder ladder, topic-titled from the syllabus in order. Safe to
 * re-run: slots already holding a class are skipped. One summary announcement
 * goes to the batch (not one message per class). Driven by the CRM.
 */
final class ScheduleClassSeries extends Command
{
    use ResolvesCrmTargets;

    protected $signature = 'class:schedule-series
        {batch : Batch id or number}
        {--weekdays=1,2,3,4,5 : ISO weekdays holding class, comma-separated (1=Mon … 7=Sun)}
        {--time=19:00 : Class start time (HH:MM, 24h)}
        {--duration=90 : Length in minutes}
        {--count= : Number of classes to create}
        {--until= : ...or schedule pattern days up to this date (Y-m-d)}
        {--start= : First calendar day to consider (defaults to the batch start or tomorrow)}
        {--title-prefix= : Title prefix when not mapping topics (default "Class")}
        {--no-topics : Number the classes instead of titling them from the syllabus}
        {--no-announce : Skip the one summary announcement to the batch}';

    protected $description = 'Schedule a full class series for a batch (Zoom + reminders per class, syllabus-titled)';

    public function handle(ScheduleBatchSeries $series, Messenger $messenger): int
    {
        $batch = $this->findBatch((string) $this->argument('batch'));

        if ($batch === null) {
            $this->error("Batch '{$this->argument('batch')}' not found.");

            return self::FAILURE;
        }

        $weekdays = array_values(array_filter(array_map(
            'intval',
            explode(',', (string) $this->option('weekdays')),
        ), fn (int $d) => $d >= 1 && $d <= 7));

        if ($weekdays === []) {
            $this->error('No valid weekdays given — use 1=Mon … 7=Sun, comma-separated.');

            return self::FAILURE;
        }

        $start = CarbonImmutable::parse(
            (string) ($this->option('start') ?: ($batch->starts_on?->toDateString() ?? now()->addDay()->toDateString()))
        )->startOfDay();

        if ($start->isPast() && ! $start->isToday()) {
            $start = CarbonImmutable::tomorrow();
        }

        if ($this->option('count') === null && ! $this->option('until')) {
            $this->error('Give --count (number of classes) or --until (end date).');

            return self::FAILURE;
        }

        return $this->runForTenant($batch->tenant_id, function () use ($batch, $weekdays, $start, $series, $messenger): int {
            $count = $this->classCount($batch, $weekdays, $start);

            if ($count < 1) {
                $this->warn('Nothing to schedule — no open class slots in the chosen range.');

                return self::SUCCESS;
            }
            $created = $series->handle(
                $batch,
                weekdays: $weekdays,
                time: (string) $this->option('time'),
                durationMinutes: (int) $this->option('duration'),
                count: $count,
                startDate: $start,
                titlePrefix: $this->option('title-prefix') ?: null,
                mapTopics: ! $this->option('no-topics'),
            );

            if ($created === []) {
                $this->warn('No classes created — every slot in range was already scheduled.');

                return self::SUCCESS;
            }

            $first = $created[0];
            $last = $created[count($created) - 1];

            $this->info(sprintf(
                'Scheduled %d class(es) for %s: %s → %s at %s (%d min each), Zoom + reminders armed.',
                count($created),
                $batch->number,
                $first->scheduled_start->format('D d M Y'),
                $last->scheduled_start->format('D d M Y'),
                $this->option('time'),
                (int) $this->option('duration'),
            ));

            if (! $this->option('no-announce')) {
                $dayNames = ['', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];
                $pattern = implode('/', array_map(fn (int $d) => $dayNames[$d], $weekdays));

                $summary = sprintf(
                    'Your class calendar is ready — %d classes every %s at %s, from %s to %s. Check your dashboard for the full schedule.',
                    count($created),
                    $pattern,
                    $first->scheduled_start->format('h:i A'),
                    $first->scheduled_start->format('d M'),
                    $last->scheduled_start->format('d M Y'),
                );

                $occupying = array_map(fn (BatchMemberStatus $s) => $s->value, BatchMemberStatus::occupying());
                $members = $batch->members()->whereIn('status', $occupying)->with('student')->get();

                foreach ($members as $member) {
                    if ($member->student === null) {
                        continue;
                    }

                    $vars = [
                        'name' => $member->student->name,
                        'batch' => $batch->number,
                        'subject' => 'Your class schedule is ready',
                        'message' => $summary,
                    ];

                    foreach ([MessageChannel::WhatsApp, MessageChannel::Email] as $channel) {
                        $messenger->send($member->student, 'batch_announcement', $vars, ['channel' => $channel]);
                    }
                }

                $this->info("Schedule summary announced to {$members->count()} student(s).");
            }

            return self::SUCCESS;
        });
    }

    /**
     * How many classes to create. --count means "add this many new classes".
     * --until counts only the OPEN pattern slots between start and that date
     * (inclusive) — ScheduleBatchSeries treats count as new-classes-to-create
     * and would otherwise run past the end date when slots are already booked.
     * Runs within the tenant context (queries live_sessions).
     *
     * @param  list<int>  $weekdays
     */
    private function classCount(Batch $batch, array $weekdays, CarbonImmutable $start): int
    {
        if ($this->option('count') !== null) {
            return (int) $this->option('count');
        }

        $until = CarbonImmutable::parse((string) $this->option('until'))->endOfDay();
        $count = 0;

        for ($day = $start; $day->lessThanOrEqualTo($until); $day = $day->addDay()) {
            if (! in_array($day->isoWeekday(), $weekdays, true)) {
                continue;
            }

            $slot = $day->setTimeFromTimeString((string) $this->option('time'));

            $taken = LiveSession::query()
                ->where('batch_id', $batch->id)
                ->where('scheduled_start', $slot)
                ->where('status', '!=', LiveSessionStatus::Cancelled->value)
                ->exists();

            if ($slot->isFuture() && ! $taken) {
                $count++;
            }
        }

        return $count;
    }
}
