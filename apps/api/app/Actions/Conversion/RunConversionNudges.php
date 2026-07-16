<?php

declare(strict_types=1);

namespace App\Actions\Conversion;

use App\Actions\Crm\CreateTask;
use App\Enums\BatchMemberStatus;
use App\Enums\BatchType;
use App\Enums\FeePlanStatus;
use App\Enums\InstalmentStatus;
use App\Models\BatchMember;
use App\Models\ConversionNudge;
use App\Models\CrmTask;
use App\Models\FeePlan;
use App\Models\Lead;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Crm\PhoneNormalizer;
use App\Support\Messaging\Messenger;
use App\Support\Tenancy\TenantContext;
use Illuminate\Support\Carbon;

/**
 * Non-payer nudge ladder (PRD §5 Stage 3), run daily by `conversions:run-nudges`.
 * For each converted-but-unpaid student (active fee plan on a paid batch, member
 * still Reserved/Payment-Pending) it re-sends the payment link on the config
 * cadence (24h/72h/7d) with a batch-scarcity line, and raises a counselor task at
 * the counselor-task day. Auto-pauses if the lead has replied since conversion;
 * stops once the first instalment is paid (member becomes Enrolled). Idempotent —
 * deduped via conversion_nudges.
 *
 * @phpstan-type NudgeSummary array{nudges: int, tasks: int}
 */
final readonly class RunConversionNudges
{
    public function __construct(
        private Messenger $messenger,
        private CreateTask $createTask,
    ) {}

    /**
     * @return NudgeSummary
     */
    public function handle(?Carbon $today = null): array
    {
        $today = ($today ?? Carbon::today())->copy()->startOfDay();
        $summary = ['nudges' => 0, 'tasks' => 0];

        $tenantIds = FeePlan::query()->withoutGlobalScopes()
            ->where('status', FeePlanStatus::Active->value)
            ->distinct()
            ->pluck('tenant_id');

        foreach ($tenantIds as $tenantId) {
            $tenant = Tenant::query()->find($tenantId);
            if ($tenant === null) {
                continue;
            }

            app(TenantContext::class)->run($tenant, function () use ($today, &$summary): void {
                FeePlan::query()
                    ->where('status', FeePlanStatus::Active->value)
                    ->with(['student', 'batch', 'instalments'])
                    ->get()
                    ->each(function (FeePlan $plan) use ($today, &$summary): void {
                        $this->processPlan($plan, $today, $summary);
                    });
            });
        }

        return $summary;
    }

    /**
     * @param  NudgeSummary  $summary
     */
    private function processPlan(FeePlan $plan, Carbon $today, array &$summary): void
    {
        $student = $plan->student;
        $batch = $plan->batch;
        if ($student === null || $batch === null || $batch->type !== BatchType::Paid) {
            return;
        }

        // Only converted-but-unpaid members (stops once enrolled on payment).
        $member = BatchMember::query()
            ->where('batch_id', $batch->id)->where('user_id', $student->id)->first();
        if ($member === null || ! in_array($member->status, [BatchMemberStatus::Reserved, BatchMemberStatus::PaymentPending], true)) {
            return;
        }

        $convertedAt = $plan->created_at;
        $lead = $this->correlateLead($student);

        // Auto-pause: the lead replied after conversion — a human is handling it.
        if ($lead !== null && $lead->last_replied_at !== null && $lead->last_replied_at->gt($convertedAt)) {
            return;
        }

        $daysSince = (int) $convertedAt->copy()->startOfDay()->diffInDays($today, false);
        if ($daysSince < 0) {
            return;
        }

        $nudgeDays = (array) config('fees.conversion.nudge_days', [1, 3, 7]);
        $taskDay = (int) config('fees.conversion.counselor_task_day', 3);

        if (in_array($daysSince, $nudgeDays, true)) {
            $this->sendNudge($plan, $student, $lead, "day-{$daysSince}", $summary);
        }

        if ($daysSince === $taskDay) {
            $this->raiseTask($plan, $student, $lead, $summary);
        }
    }

    /**
     * @param  NudgeSummary  $summary
     */
    private function sendNudge(FeePlan $plan, User $student, ?Lead $lead, string $rung, array &$summary): void
    {
        $nudge = ConversionNudge::query()->firstOrCreate(
            ['fee_plan_id' => $plan->id, 'rung' => $rung],
            ['tenant_id' => $plan->tenant_id, 'channel' => 'log', 'sent_at' => now()],
        );
        if (! $nudge->wasRecentlyCreated) {
            return;
        }

        $batch = $plan->batch;
        $link = $plan->instalments
            ->firstWhere('status', InstalmentStatus::Pending)?->payment_link_url ?? '';
        $seats = $batch->capacity !== null ? max(0, $batch->capacity - $batch->occupiedSeats()) : '—';

        $this->messenger->send($student, 'conversion_nudge', [
            'name' => $student->name,
            'batch' => $batch->number,
            'seats' => (string) $seats,
            'starts' => $batch->starts_on?->format('d M') ?? 'soon',
            'link' => $link,
        ], ['lead' => $lead]);

        $summary['nudges']++;
    }

    /**
     * @param  NudgeSummary  $summary
     */
    private function raiseTask(FeePlan $plan, User $student, ?Lead $lead, array &$summary): void
    {
        $nudge = ConversionNudge::query()->firstOrCreate(
            ['fee_plan_id' => $plan->id, 'rung' => 'task'],
            ['tenant_id' => $plan->tenant_id, 'channel' => 'log', 'sent_at' => now()],
        );
        if (! $nudge->wasRecentlyCreated) {
            return;
        }

        if ($lead === null || $lead->assignee === null) {
            return;
        }

        $this->createTask->handle(
            $lead,
            $lead->assignee,
            "Call {$student->name} — bootcamp converted, payment pending",
            now(),
            null,
            CrmTask::SOURCE_CONVERSION,
        );
        $summary['tasks']++;
    }

    private function correlateLead(User $student): ?Lead
    {
        $phone = $student->phone !== null ? PhoneNormalizer::normalize($student->phone) : null;
        $hasPhone = $phone !== null && $phone !== '';
        $hasEmail = $student->email !== null && $student->email !== '';
        if (! $hasPhone && ! $hasEmail) {
            return null;
        }

        return Lead::query()
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
    }
}
