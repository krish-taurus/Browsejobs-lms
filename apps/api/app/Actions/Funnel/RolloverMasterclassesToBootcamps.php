<?php

declare(strict_types=1);

namespace App\Actions\Funnel;

use App\Actions\Batches\CreateBatch;
use App\Actions\Conversion\CompleteMasterclass;
use App\Actions\LiveClasses\ScheduleBatchSeries;
use App\Actions\Roster\AddStudentToBatch;
use App\Enums\BatchMemberStatus;
use App\Enums\BatchType;
use App\Models\Batch;
use Carbon\CarbonImmutable;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

/**
 * Weekly funnel stage 2 (runs within a tenant context): every masterclass batch
 * whose Saturday has passed is completed, and its attendees flow straight into a
 * fresh 7-day bootcamp (Monday → Sunday). The linked paid batch is created up
 * front so that CompleteBootcamp can convert attendees the moment the bootcamp
 * ends. Idempotent: completing the masterclass removes it from the query.
 */
final readonly class RolloverMasterclassesToBootcamps
{
    public function __construct(
        private CompleteMasterclass $completeMasterclass,
        private CreateBatch $createBatch,
        private AddStudentToBatch $addStudent,
        private ScheduleBatchSeries $scheduleSeries,
        private SendBatchCredentials $credentials,
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
            $occupying = array_map(fn (BatchMemberStatus $s) => $s->value, BatchMemberStatus::occupying());
            $members = $masterclass->members()->whereIn('status', $occupying)->with('student')->get();

            $this->completeMasterclass->handle($masterclass);
            $rolled++;

            if ($members->isEmpty() || $masterclass->course === null) {
                continue;
            }

            $bootcampStart = Carbon::parse($masterclass->starts_on)->next(Carbon::MONDAY); // the Monday after (Sat or Sun masterclass)
            $bootcampEnd = $bootcampStart->copy()->addDays(6);                             // 7 days inclusive

            // linked_source_batch_id records the full chain: bootcamp → its
            // masterclass, and (below) paid batch → its bootcamp.
            $bootcamp = $this->createBatch->handle($masterclass->course, BatchType::Bootcamp, [
                'starts_on' => $bootcampStart->toDateString(),
                'ends_on' => $bootcampEnd->toDateString(),
                'linked_source_batch_id' => $masterclass->id,
            ]);

            // Created now so CompleteBootcamp finds its conversion target later.
            $this->createBatch->handle($masterclass->course, BatchType::Paid, [
                'linked_source_batch_id' => $bootcamp->id,
                'starts_on' => $bootcampEnd->copy()->addDay()->toDateString(),
            ]);

            foreach ($members as $member) {
                if ($member->student === null) {
                    continue;
                }

                try {
                    $this->addStudent->handle($bootcamp, $member->student, BatchMemberStatus::Enrolled);
                    $enrolled++;

                    // "You're in the bootcamp" — batch number + how to sign in
                    // (OTP to this number/email; no passwords are sent).
                    $this->credentials->handle($member->student, $bootcamp);
                } catch (ValidationException) {
                    // duplicate / capacity — skip
                }
            }

            // Fully automatic: the bootcamp's 7 daily classes are scheduled up
            // front (topic-titled from the syllabus when it has topics), each
            // with its own Zoom meeting and student reminder ladder.
            $this->scheduleSeries->handle(
                $bootcamp,
                weekdays: [1, 2, 3, 4, 5, 6, 7],
                time: (string) config('funnel.class_time', '19:00'),
                durationMinutes: (int) config('funnel.class_duration_minutes', 90),
                count: 7,
                startDate: CarbonImmutable::parse($bootcampStart->toDateString()),
                titlePrefix: 'Bootcamp Day',
                mapTopics: true,
            );
        }

        return ['rolled' => $rolled, 'enrolled' => $enrolled];
    }
}
