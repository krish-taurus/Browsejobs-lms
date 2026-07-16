<?php

declare(strict_types=1);

namespace App\Actions\Payments;

use App\Enums\BatchMemberStatus;
use App\Enums\FeePlanStatus;
use App\Enums\FeePlanType;
use App\Enums\LedgerDirection;
use App\Models\Batch;
use App\Models\BatchMember;
use App\Models\FeePlan;
use App\Models\Instalment;
use App\Models\LedgerEntry;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Audit\AuditLogger;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Creates a fee plan for a student sitting Reserved (or Payment Pending) in a
 * paid batch (PRD §6.8 / §14.4). Amounts are server-owned from config/fees.php —
 * never client-sent. Builds the instalment schedule and writes ledger debits.
 */
final readonly class CreateFeePlan
{
    public function __construct(
        private PreviewSchedule $schedule,
        private AuditLogger $audit,
    ) {}

    public function handle(
        Tenant $tenant,
        User $student,
        Batch $batch,
        FeePlanType $type,
        int $emiCount = 1,
        int $discountPaise = 0,
        ?User $actor = null,
    ): FeePlan {
        if ($batch->tenant_id !== $tenant->id || $student->tenant_id !== $tenant->id) {
            throw ValidationException::withMessages(['batch' => 'Batch or student belongs to another tenant.']);
        }

        $member = BatchMember::query()
            ->where('batch_id', $batch->id)
            ->where('user_id', $student->id)
            ->first();

        if ($member === null || ! in_array($member->status, [BatchMemberStatus::Reserved, BatchMemberStatus::PaymentPending], true)) {
            throw ValidationException::withMessages([
                'student' => 'Student must be Reserved / Payment Pending in this batch to raise a fee plan.',
            ]);
        }

        $emiOptions = (array) config('fees.emi_options', [1, 2, 3]);
        if ($type === FeePlanType::Emi && ! in_array($emiCount, $emiOptions, true)) {
            throw ValidationException::withMessages(['emi_count' => 'Unsupported EMI count.']);
        }

        $total = (int) config('fees.registration_paise');
        $discountPaise = max(0, min($discountPaise, $total));

        return DB::transaction(function () use ($tenant, $student, $batch, $type, $emiCount, $discountPaise, $total, $actor): FeePlan {
            $feePlan = FeePlan::query()->create([
                'tenant_id' => $tenant->id,
                'user_id' => $student->id,
                'batch_id' => $batch->id,
                'type' => $type->value,
                'total_paise' => $total,
                'discount_paise' => $discountPaise,
                'gst_rate_bps' => (int) config('fees.gst_rate_bps', 1800),
                'currency' => (string) config('fees.currency', 'INR'),
                'status' => FeePlanStatus::Active->value,
                'created_by' => $actor?->id,
            ]);

            $rows = $this->schedule->handle($type, $emiCount, $total, $discountPaise, Carbon::today());

            foreach ($rows as $row) {
                Instalment::query()->create([
                    'tenant_id' => $tenant->id,
                    'fee_plan_id' => $feePlan->id,
                    'seq' => $row['seq'],
                    'amount_paise' => $row['amount_paise'],
                    'due_on' => $row['due_on'],
                ]);

                LedgerEntry::query()->create([
                    'tenant_id' => $tenant->id,
                    'user_id' => $student->id,
                    'fee_plan_id' => $feePlan->id,
                    'direction' => LedgerDirection::Debit->value,
                    'amount_paise' => $row['amount_paise'],
                    'description' => "Instalment {$row['seq']} due",
                    'occurred_at' => now(),
                ]);
            }

            $this->audit->log(
                action: 'fees.plan_created',
                target: $feePlan,
                metadata: ['type' => $type->value, 'emi_count' => count($rows), 'total_paise' => $total],
                actor: $actor,
            );

            return $feePlan->load('instalments');
        });
    }
}
