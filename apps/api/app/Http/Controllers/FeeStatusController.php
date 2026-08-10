<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\Payments\CreateFeePlan;
use App\Actions\Payments\PreviewSchedule;
use App\Actions\Payments\SendPaymentLink;
use App\Enums\BatchMemberStatus;
use App\Enums\BatchType;
use App\Enums\FeePlanType;
use App\Enums\InstalmentStatus;
use App\Models\BatchMember;
use App\Models\FeePlan;
use App\Models\User;
use App\Support\Fees\DunningStatus;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Student-facing fee status (PRD §6.8 dashboard-native awareness). Backs the fee
 * widget + the "blocked, pay to unblock" screen: next-due amount + countdown,
 * escalation/block level, and a ready-to-pay Razorpay link for the next
 * instalment.
 */
final class FeeStatusController extends Controller
{
    /**
     * What a student who has not paid yet is looking at: the course fee and the
     * ways to clear it, each with its real schedule (PRD §6.8 "schedule
     * previewed before confirm").
     *
     * A seat in a paid batch used to owe nothing until an admin raised a plan
     * by hand in the LMS panel, so the dashboard showed no fees at all. The
     * offer is now derived from the seat itself and the student picks.
     */
    public function offer(Request $request, PreviewSchedule $schedule): JsonResponse
    {
        $student = $request->user();
        $tenant = $student->tenant;

        if ($tenant === null) {
            return response()->json(['data' => ['has_plan' => false, 'offer' => null]]);
        }

        return app(TenantContext::class)->run($tenant, function () use ($student, $schedule): JsonResponse {
            if ($this->activePlan($student) !== null) {
                return response()->json(['data' => ['has_plan' => true, 'offer' => null]]);
            }

            $member = $this->unpaidSeat($student);

            if ($member === null) {
                return response()->json(['data' => ['has_plan' => false, 'offer' => null]]);
            }

            $batch = $member->batch;
            $total = CreateFeePlan::totalFor($batch);

            $options = [[
                'type' => FeePlanType::Single->value,
                'emi_count' => 1,
                'label' => 'Pay in full',
                'rows' => $schedule->handle(FeePlanType::Single, 1, $total),
            ]];

            foreach ((array) config('fees.emi_options', [1, 2, 3]) as $count) {
                if ((int) $count < 2) {
                    continue; // "1 EMI" is just paying in full
                }

                $options[] = [
                    'type' => FeePlanType::Emi->value,
                    'emi_count' => (int) $count,
                    'label' => $count.' monthly instalments',
                    'rows' => $schedule->handle(FeePlanType::Emi, (int) $count, $total),
                ];
            }

            return response()->json(['data' => [
                'has_plan' => false,
                'offer' => [
                    'batch' => $batch->number,
                    'course' => $batch->course?->name,
                    'total_paise' => $total,
                    'currency' => (string) config('fees.currency', 'INR'),
                    'options' => $options,
                ],
            ]]);
        });
    }

    /**
     * The student commits to one of those options. Only the shape of the plan
     * is theirs to choose — the amount is read server-side from the course, so
     * nothing a client sends can change what is owed.
     */
    public function choose(Request $request, CreateFeePlan $create): JsonResponse
    {
        $validated = $request->validate([
            'type' => ['required', 'in:single,emi'],
            'emi_count' => ['nullable', 'integer', 'min:1', 'max:12'],
        ]);

        $student = $request->user();
        $tenant = $student->tenant;

        abort_if($tenant === null, 422, 'No tenant for this account.');

        return app(TenantContext::class)->run($tenant, function () use ($student, $tenant, $validated, $create): JsonResponse {
            if (($existing = $this->activePlan($student)) !== null) {
                // Already committed — say so rather than raising a second plan.
                return response()->json(['data' => ['fee_plan_id' => $existing->id, 'already_had_one' => true]]);
            }

            $member = $this->unpaidSeat($student);

            abort_if($member === null, 422, 'You have no seat awaiting payment.');

            $type = FeePlanType::from($validated['type']);
            $plan = $create->handle(
                tenant: $tenant,
                student: $student,
                batch: $member->batch,
                type: $type,
                emiCount: $type === FeePlanType::Emi ? (int) ($validated['emi_count'] ?? 2) : 1,
                actor: $student,
            );

            return response()->json(['data' => ['fee_plan_id' => $plan->id, 'already_had_one' => false]], 201);
        });
    }

    /** The plan a student is currently on, if any. */
    private function activePlan(User $student): ?FeePlan
    {
        return FeePlan::query()
            ->where('user_id', $student->id)
            ->whereIn('status', ['active', 'paid'])
            ->orderByDesc('id')
            ->first();
    }

    /** A paid seat this student holds that has not been paid for yet. */
    private function unpaidSeat(User $student): ?BatchMember
    {
        return BatchMember::query()
            ->where('user_id', $student->id)
            ->whereIn('status', [BatchMemberStatus::Reserved->value, BatchMemberStatus::PaymentPending->value])
            ->whereHas('batch', fn ($q) => $q->where('type', BatchType::Paid->value))
            ->with('batch.course')
            ->orderByDesc('id')
            ->first();
    }

    public function show(Request $request, DunningStatus $dunning, SendPaymentLink $link): JsonResponse
    {
        $student = $request->user();
        $tenant = $student->tenant;

        if ($tenant === null) {
            return response()->json(['data' => ['has_plan' => false]]);
        }

        return app(TenantContext::class)->run($tenant, function () use ($student, $dunning, $link): JsonResponse {
            $plan = FeePlan::query()
                ->where('user_id', $student->id)
                ->whereIn('status', ['active', 'paid'])
                ->with(['instalments' => fn ($q) => $q->orderBy('seq'), 'batch:id,number'])
                ->orderByDesc('id')
                ->first();

            if ($plan === null) {
                return response()->json(['data' => ['has_plan' => false]]);
            }

            // Ensure the next unpaid instalment has a payable link.
            $next = $plan->instalments->firstWhere('status', InstalmentStatus::Pending);
            if ($next !== null && ($next->payment_link_url === null || $next->payment_link_url === '')) {
                $link->handle($next);
                $plan->load(['instalments' => fn ($q) => $q->orderBy('seq')]);
            }

            $snapshot = $dunning->for($plan);

            return response()->json(['data' => [
                'has_plan' => true,
                'fee_plan_id' => $plan->id,
                'batch' => $plan->batch?->number,
                'status' => $plan->status->value,
                'outstanding_paise' => $snapshot->outstandingPaise,
                'next_amount_paise' => $snapshot->nextAmountPaise,
                'next_due_on' => $snapshot->nextDueOn,
                'days_to_due' => $snapshot->daysToDue,
                'overdue' => $snapshot->overdue,
                'block' => $snapshot->blockLevel,
                'pay_url' => $snapshot->payUrl,
                'instalments' => $plan->instalments->map(fn ($i) => [
                    'seq' => $i->seq,
                    'amount_paise' => $i->amount_paise,
                    'due_on' => $i->due_on?->toDateString(),
                    'status' => $i->status->value,
                ])->all(),
            ]]);
        });
    }
}
