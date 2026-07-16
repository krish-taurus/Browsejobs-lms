<?php

declare(strict_types=1);

namespace App\Actions\Support;

use App\Enums\BatchMemberStatus;
use App\Models\AccessBlock;
use App\Models\Attendance;
use App\Models\BatchMember;
use App\Models\Instalment;
use App\Models\StudentScore;
use App\Models\Ticket;
use App\Models\User;

/**
 * Assembles the staff workspace's student-context sidebar (PRD §6.13) so staff
 * never have to ask "which batch are you in?": active batch, fee status,
 * attendance, risk score (P3.2), and recent tickets.
 */
final readonly class TicketStudentContext
{
    /**
     * @return array<string, mixed>
     */
    public function handle(User $student): array
    {
        $occupying = array_map(fn (BatchMemberStatus $s) => $s->value, BatchMemberStatus::occupying());

        $membership = BatchMember::query()
            ->where('user_id', $student->id)
            ->whereIn('status', $occupying)
            ->with(['batch:id,number,course_id', 'batch.course:id,name'])
            ->orderByDesc('id')
            ->first();

        $outstanding = (int) Instalment::query()
            ->whereHas('feePlan', fn ($q) => $q->where('user_id', $student->id))
            ->where('status', '!=', 'paid')
            ->sum('amount_paise');

        $blocked = AccessBlock::query()
            ->where('user_id', $student->id)
            ->whereNull('lifted_at')
            ->exists();

        $attendancePct = (int) round((float) Attendance::query()
            ->where('user_id', $student->id)
            ->avg('attended_pct'));

        $riskScore = StudentScore::query()->where('user_id', $student->id)->value('risk_dropout');

        $recent = Ticket::query()
            ->where('student_id', $student->id)
            ->orderByDesc('id')
            ->limit(5)
            ->get(['id', 'reference', 'category', 'status']);

        return [
            'student' => [
                'id' => $student->id,
                'name' => $student->name,
                'phone' => $student->phone,
                'email' => $student->email,
            ],
            'batch' => $membership?->batch === null ? null : [
                'number' => $membership->batch->number,
                'course' => $membership->batch->course?->name,
                'status' => $membership->status,
            ],
            'fee' => ['outstanding_paise' => $outstanding, 'blocked' => $blocked],
            'attendance_pct' => $attendancePct,
            'risk_score' => $riskScore === null ? null : (int) $riskScore,
            'recent_tickets' => $recent->map(fn (Ticket $t) => [
                'id' => $t->id,
                'reference' => $t->reference,
                'category' => $t->category->value,
                'status' => $t->status->value,
            ])->all(),
        ];
    }
}
