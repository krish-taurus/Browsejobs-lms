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
 * Silo roster add: places one student in a batch, enforcing the capacity guard
 * and the one-membership-per-batch rule, and writing a roster audit entry.
 */
final readonly class AddStudentToBatch
{
    public function __construct(private AuditLogger $audit) {}

    public function handle(
        Batch $batch,
        User $student,
        BatchMemberStatus $status = BatchMemberStatus::Reserved,
        ?User $actor = null,
    ): BatchMember {
        if ($student->tenant_id !== $batch->tenant_id) {
            throw ValidationException::withMessages([
                'user' => 'Student and batch belong to different tenants.',
            ]);
        }

        if ($batch->members()->where('user_id', $student->id)->exists()) {
            throw ValidationException::withMessages([
                'user' => 'This student is already in the batch.',
            ]);
        }

        if (! $batch->hasCapacityFor(1)) {
            throw ValidationException::withMessages([
                'batch' => 'This batch is at capacity.',
            ]);
        }

        $member = BatchMember::query()->create([
            'tenant_id' => $batch->tenant_id,
            'batch_id' => $batch->id,
            'user_id' => $student->id,
            'status' => $status->value,
            'enrolled_at' => $status === BatchMemberStatus::Enrolled ? now() : null,
        ]);

        $this->audit->log(
            action: 'roster.member_added',
            target: $member,
            metadata: ['batch' => $batch->number, 'status' => $status->value],
            actor: $actor,
        );

        return $member;
    }
}
