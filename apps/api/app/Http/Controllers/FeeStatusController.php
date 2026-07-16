<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\Payments\SendPaymentLink;
use App\Enums\InstalmentStatus;
use App\Models\FeePlan;
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
