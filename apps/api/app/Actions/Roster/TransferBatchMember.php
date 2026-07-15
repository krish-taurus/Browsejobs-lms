<?php

declare(strict_types=1);

namespace App\Actions\Roster;

use App\Enums\BatchMemberStatus;
use App\Models\Batch;
use App\Models\BatchMember;
use App\Models\User;
use App\Support\Audit\AuditLogger;
use Illuminate\Validation\ValidationException;

/**
 * Transfers a student from one batch to another with a reason, marking the
 * source membership `transferred` and creating a fresh membership in the target
 * (capacity-guarded). The move is audited.
 */
final readonly class TransferBatchMember
{
    public function __construct(private AuditLogger $audit) {}

    public function handle(BatchMember $member, Batch $target, string $reason, ?User $actor = null): BatchMember
    {
        if ($member->batch_id === $target->id) {
            throw ValidationException::withMessages([
                'target' => 'The student is already in this batch.',
            ]);
        }

        if ($target->members()->where('user_id', $member->user_id)->exists()) {
            throw ValidationException::withMessages([
                'target' => 'The student is already in the target batch.',
            ]);
        }

        if (! $target->hasCapacityFor(1)) {
            throw ValidationException::withMessages([
                'target' => 'The target batch is at capacity.',
            ]);
        }

        $sourceNumber = $member->batch->number;
        $member->update(['status' => BatchMemberStatus::Transferred]);

        $moved = BatchMember::query()->create([
            'tenant_id' => $target->tenant_id,
            'batch_id' => $target->id,
            'user_id' => $member->user_id,
            'status' => BatchMemberStatus::Reserved->value,
        ]);

        $this->audit->log(
            action: 'roster.member_transferred',
            target: $moved,
            metadata: ['from' => $sourceNumber, 'to' => $target->number, 'reason' => $reason],
            actor: $actor,
        );

        return $moved;
    }
}
