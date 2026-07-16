<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Actions\Crm\MoveLeadStage;
use App\Enums\BatchMemberStatus;
use App\Models\BatchMember;
use App\Models\FeePlan;
use App\Models\Lead;
use App\Models\LeadStage;
use App\Models\Tenant;
use App\Support\Audit\AuditLogger;
use App\Support\Crm\PhoneNormalizer;
use App\Support\Tenancy\TenantContext;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * On first-instalment capture, flips the student's batch membership
 * Reserved/Payment-Pending → Enrolled (sets `enrolled_at`) and moves the matching
 * CRM lead to the Enrolled stage (PRD §5 Stage 3 / BUILD-PLAYBOOK P2.2). The lead
 * is correlated by normalized phone / email (no FK exists). Idempotent: an
 * already-enrolled member is skipped and MoveLeadStage no-ops if already there.
 */
final class FlipEnrolment implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(public readonly int $feePlanId) {}

    public function handle(MoveLeadStage $moveStage, AuditLogger $audit): void
    {
        $feePlan = FeePlan::query()->withoutGlobalScopes()->with('student')->find($this->feePlanId);
        if ($feePlan === null) {
            return;
        }

        $tenant = Tenant::query()->find($feePlan->tenant_id);
        if ($tenant === null) {
            return;
        }

        app(TenantContext::class)->run($tenant, function () use ($feePlan, $moveStage, $audit): void {
            $member = BatchMember::query()
                ->where('batch_id', $feePlan->batch_id)
                ->where('user_id', $feePlan->user_id)
                ->first();

            if ($member !== null && in_array($member->status, [BatchMemberStatus::Reserved, BatchMemberStatus::PaymentPending], true)) {
                $member->status = BatchMemberStatus::Enrolled;
                $member->enrolled_at = now();
                $member->save();

                $audit->log(
                    action: 'fees.enrolled',
                    target: $member,
                    metadata: ['batch_id' => $feePlan->batch_id, 'fee_plan_id' => $feePlan->id],
                );
            }

            $this->flipLeadStage($feePlan, $moveStage);
        });
    }

    private function flipLeadStage(FeePlan $feePlan, MoveLeadStage $moveStage): void
    {
        $student = $feePlan->student;
        if ($student === null) {
            return;
        }

        $phone = $student->phone !== null ? PhoneNormalizer::normalize($student->phone) : null;
        $hasPhone = $phone !== null && $phone !== '';
        $hasEmail = $student->email !== null && $student->email !== '';
        if (! $hasPhone && ! $hasEmail) {
            return; // nothing to correlate on
        }

        $lead = Lead::query()
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

        $stage = LeadStage::query()->where('slug', 'enrolled')->first();

        if ($lead !== null && $stage !== null) {
            $moveStage->handle($lead, $stage);
        }
    }
}
