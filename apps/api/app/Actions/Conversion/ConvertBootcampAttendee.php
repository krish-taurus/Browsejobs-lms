<?php

declare(strict_types=1);

namespace App\Actions\Conversion;

use App\Actions\Crm\MoveLeadStage;
use App\Actions\Roster\AddStudentToBatch;
use App\Enums\BatchMemberStatus;
use App\Enums\BatchType;
use App\Models\Batch;
use App\Models\BatchMember;
use App\Models\Lead;
use App\Models\LeadStage;
use App\Models\User;
use App\Support\Audit\AuditLogger;
use App\Support\Crm\PhoneNormalizer;
use Illuminate\Validation\ValidationException;

/**
 * Converts one bootcamp attendee to the linked paid batch (PRD §5 Stage 3):
 * assigns them Reserved — Payment Pending and flips their CRM lead to the
 * `reserved` stage. Idempotent — a student already in the paid batch is left as
 * is. The admin raises the fee plan + link separately.
 */
final readonly class ConvertBootcampAttendee
{
    public function __construct(
        private AddStudentToBatch $addStudent,
        private MoveLeadStage $moveStage,
        private AuditLogger $audit,
    ) {}

    public function handle(BatchMember $bootcampMember, ?User $actor = null): BatchMember
    {
        $bootcamp = $bootcampMember->batch;

        $paid = Batch::query()
            ->where('linked_source_batch_id', $bootcamp->id)
            ->where('type', BatchType::Paid->value)
            ->first();

        if ($paid === null) {
            throw ValidationException::withMessages([
                'batch' => 'No paid batch is linked to this bootcamp.',
            ]);
        }

        $student = $bootcampMember->student;

        $member = BatchMember::query()
            ->where('batch_id', $paid->id)
            ->where('user_id', $student->id)
            ->first();

        if ($member === null) {
            $member = $this->addStudent->handle($paid, $student, BatchMemberStatus::Reserved, $actor);
        }

        $this->flipLeadStage($student, $actor);

        $this->audit->log(
            action: 'conversion.assigned',
            target: $member,
            metadata: ['bootcamp_batch_id' => $bootcamp->id, 'paid_batch_id' => $paid->id],
            actor: $actor,
        );

        return $member;
    }

    private function flipLeadStage(User $student, ?User $actor): void
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
            $this->moveStage->handle($lead, $stage, $actor);
        }
    }
}
