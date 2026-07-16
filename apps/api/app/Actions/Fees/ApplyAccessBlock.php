<?php

declare(strict_types=1);

namespace App\Actions\Fees;

use App\Actions\Crm\CreateTask;
use App\Enums\AccessBlockLevel;
use App\Models\AccessBlock;
use App\Models\FeePlan;
use App\Models\Lead;
use App\Models\User;
use App\Support\Audit\AuditLogger;
use App\Support\Crm\PhoneNormalizer;
use App\Support\Notifications\FeeNotifier;

/**
 * Applies (or promotes) a fee-driven access block on a student (PRD §6.8).
 * Idempotent: a student has at most one active block; a soft block promotes to
 * hard, an equal-or-lower request is a no-op. A hard block also raises a
 * counselor call task against the student's matching CRM lead (best-effort).
 */
final readonly class ApplyAccessBlock
{
    public function __construct(
        private AuditLogger $audit,
        private FeeNotifier $notifier,
        private CreateTask $createTask,
    ) {}

    public function handle(User $student, AccessBlockLevel $level, ?FeePlan $plan = null, string $reason = ''): AccessBlock
    {
        $existing = AccessBlock::query()
            ->where('user_id', $student->id)
            ->whereNull('lifted_at')
            ->first();

        if ($existing !== null) {
            // Already at or above the requested level → no-op.
            if ($existing->level === AccessBlockLevel::Hard || $existing->level === $level) {
                return $existing;
            }
            // Promote soft → hard.
            $existing->level = $level;
            $existing->reason = $reason !== '' ? $reason : $existing->reason;
            $existing->save();
            $block = $existing;
        } else {
            $block = AccessBlock::query()->create([
                'tenant_id' => $student->tenant_id,
                'user_id' => $student->id,
                'batch_id' => $plan?->batch_id,
                'fee_plan_id' => $plan?->id,
                'level' => $level->value,
                'reason' => $reason,
                'blocked_at' => now(),
            ]);
        }

        $this->audit->log(
            action: 'fees.access_blocked',
            target: $block,
            metadata: ['level' => $level->value, 'reason' => $reason],
        );
        $this->notifier->blocked($student, $block);

        if ($level === AccessBlockLevel::Hard) {
            $this->raiseCounselorTask($student);
        }

        return $block;
    }

    private function raiseCounselorTask(User $student): void
    {
        $phone = $student->phone !== null ? PhoneNormalizer::normalize($student->phone) : null;
        $hasPhone = $phone !== null && $phone !== '';
        $hasEmail = $student->email !== null && $student->email !== '';
        if (! $hasPhone && ! $hasEmail) {
            return; // nothing to correlate on
        }

        $lead = Lead::query()
            ->whereNull('merged_into_id')
            ->whereNotNull('assigned_to')
            ->where(function ($q) use ($phone, $hasPhone, $hasEmail, $student) {
                if ($hasPhone) {
                    $q->where('phone_normalized', $phone);
                }
                if ($hasEmail) {
                    $q->orWhere('email', $student->email);
                }
            })
            ->first();

        if ($lead === null || $lead->assignee === null) {
            return; // no counselor to task
        }

        $this->createTask->handle(
            $lead,
            $lead->assignee,
            "Call {$student->name} — fees hard-blocked, follow up on payment",
            now(),
        );
    }
}
