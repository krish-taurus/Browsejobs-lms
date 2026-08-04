<?php

declare(strict_types=1);

namespace App\Actions\Funnel;

use App\Actions\Crm\MoveLeadStage;
use App\Actions\LiveClasses\ScheduleBatchSeries;
use App\Enums\BatchMemberStatus;
use App\Enums\BatchType;
use App\Events\BootcampCompleted;
use App\Models\Batch;
use App\Models\Lead;
use App\Models\LeadStage;
use App\Models\Topic;
use App\Models\User;
use App\Support\Audit\AuditLogger;
use App\Support\Crm\PhoneNormalizer;
use Carbon\CarbonImmutable;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

/**
 * Funnel stage 3: a cohort's 7 bootcamp days are over, so the SAME batch
 * becomes the paid batch.
 *
 * The cohort keeps its one number the whole way — masterclass → bootcamp →
 * paid. Nobody is moved between batches: every attendee stays seated and is
 * marked Payment Pending, which the fee gate reads to block class and
 * recording access until they pay. Their CRM lead moves to the `reserved`
 * stage so the dunning ladder picks them up.
 *
 * Idempotent: changing the type drops the batch out of the query.
 */
final readonly class CompleteEndedBootcamps
{
    public function __construct(
        private ScheduleBatchSeries $scheduleSeries,
        private MoveLeadStage $moveStage,
        private AuditLogger $audit,
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
                $result = $this->convertOne($bootcamp);
                $completed++;
                $converted += $result['converted'];
            } catch (ValidationException) {
                $skipped++;
            }
        }

        return ['completed' => $completed, 'converted' => $converted, 'skipped' => $skipped];
    }

    /**
     * Turn one bootcamp batch into its paid batch, whatever the date — the CRM
     * uses this to convert a cohort early.
     *
     * @return array{converted: int}
     */
    public function convertOne(Batch $batch): array
    {
        if ($batch->type !== BatchType::Bootcamp) {
            throw ValidationException::withMessages([
                'batch' => 'Only a bootcamp batch can move to paid.',
            ]);
        }

        $paidStart = $batch->ends_on !== null
            ? Carbon::parse($batch->ends_on)->addDay()
            : Carbon::today();

        // The paid stage runs open-ended: the syllabus series below defines it.
        $batch->forceFill([
            'type' => BatchType::Paid->value,
            'ends_on' => null,
        ])->save();

        $occupying = array_map(fn (BatchMemberStatus $s) => $s->value, BatchMemberStatus::occupying());
        $members = $batch->members()->whereIn('status', $occupying)->with('student')->get();

        $converted = 0;

        foreach ($members as $member) {
            if ($member->student === null) {
                continue;
            }

            // Already paying students keep their seat as-is; everyone else owes
            // the fee, so the gate holds them until the first instalment lands.
            if ($member->status !== BatchMemberStatus::PaymentPending) {
                $member->forceFill(['status' => BatchMemberStatus::PaymentPending->value])->save();
            }

            $this->flipLeadStage($member->student);
            $converted++;
        }

        $this->schedulePaidClasses($batch, $paidStart);

        $this->audit->log(
            action: 'batch.stage_advanced',
            target: $batch,
            metadata: ['from' => 'bootcamp', 'to' => 'paid', 'members' => $converted],
        );

        // Auto-request a testimonial from each attendee (Review-for-Voucher engine).
        BootcampCompleted::dispatch($batch);

        return ['converted' => $converted];
    }

    /**
     * One weekday class per syllabus topic, falling back to a fixed count when
     * the course has no topics yet. Idempotent per slot, so re-runs and manual
     * additions never double-book.
     */
    private function schedulePaidClasses(Batch $batch, Carbon $start): void
    {
        $topicCount = Topic::query()
            ->whereHas('module', fn ($q) => $q->where('course_id', $batch->course_id))
            ->count();

        $this->scheduleSeries->handle(
            $batch,
            weekdays: (array) config('funnel.paid_class_weekdays', [1, 2, 3, 4, 5]),
            time: (string) config('funnel.class_time', '19:00'),
            durationMinutes: (int) config('funnel.class_duration_minutes', 90),
            count: $topicCount > 0 ? $topicCount : (int) config('funnel.paid_class_fallback_count', 20),
            startDate: CarbonImmutable::parse($start->toDateString()),
            titlePrefix: 'Class',
            mapTopics: true,
        );
    }

    /** Mirror the conversion onto the CRM lead so dunning and reporting follow. */
    private function flipLeadStage(User $student): void
    {
        $phone = $student->phone !== null ? PhoneNormalizer::normalize($student->phone) : null;
        $hasPhone = $phone !== null && $phone !== '';
        $hasEmail = $student->email !== null && $student->email !== '';

        if (! $hasPhone && ! $hasEmail) {
            return;
        }

        $lead = Lead::query()
            ->where('tenant_id', $student->tenant_id)
            ->whereNull('merged_into_id')
            ->where(function ($q) use ($phone, $hasPhone, $hasEmail, $student) {
                if ($hasPhone) {
                    $q->where('phone_normalized', $phone);
                }
                if ($hasEmail) {
                    $q->orWhere('email', $student->email);
                }
            })
            ->orderBy('id')
            ->first();

        $stage = LeadStage::query()->where('slug', 'reserved')->first();

        if ($lead !== null && $stage !== null) {
            $this->moveStage->handle($lead, $stage);
        }
    }
}
