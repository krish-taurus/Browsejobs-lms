<?php

declare(strict_types=1);

namespace App\Actions\Roster;

use App\Enums\BatchMemberStatus;
use App\Models\BatchMember;
use App\Models\User;
use App\Support\Audit\AuditLogger;

/**
 * Removes a student from a batch with a reason. The membership is marked
 * `dropped` (kept for history, not hard-deleted) and the removal is audited.
 */
final readonly class RemoveBatchMember
{
    public function __construct(private AuditLogger $audit) {}

    public function handle(BatchMember $member, string $reason, ?User $actor = null): void
    {
        $member->update(['status' => BatchMemberStatus::Dropped]);

        $this->audit->log(
            action: 'roster.member_removed',
            target: $member,
            metadata: ['batch' => $member->batch->number, 'reason' => $reason],
            actor: $actor,
        );
    }
}
