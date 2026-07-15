<?php

declare(strict_types=1);

namespace App\Actions\LiveClasses;

use App\Models\Attendance;
use App\Models\User;
use App\Support\Audit\AuditLogger;

/**
 * Lets a trainer/admin correct an attendance record (e.g. Zoom missed a join).
 * Audited per CLAUDE.md.
 */
final readonly class OverrideAttendance
{
    public function __construct(private AuditLogger $audit) {}

    public function handle(Attendance $attendance, int $attendedPct, bool $isLate, string $reason, User $actor): Attendance
    {
        $attendance->update([
            'attended_pct' => max(0, min(100, $attendedPct)),
            'is_late' => $isLate,
            'override_by' => $actor->id,
            'override_reason' => $reason,
        ]);

        $this->audit->log(
            action: 'attendance.overridden',
            target: $attendance,
            metadata: ['attended_pct' => $attendance->attended_pct, 'is_late' => $isLate, 'reason' => $reason],
            actor: $actor,
        );

        return $attendance;
    }
}
