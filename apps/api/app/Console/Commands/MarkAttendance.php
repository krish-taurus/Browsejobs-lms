<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Console\Commands\Concerns\ResolvesCrmTargets;
use App\Models\Attendance;
use App\Support\Audit\AuditLogger;
use Illuminate\Console\Command;

/**
 * Marks a student present/absent for a class from the CRM — the manual
 * correction path when Zoom missed a join (or the student attended another
 * way). Creates the attendance row if Zoom never made one, and records the
 * override reason for the audit trail.
 */
final class MarkAttendance extends Command
{
    use ResolvesCrmTargets;

    protected $signature = 'attendance:mark
        {session : Live session id}
        {user : Student user id}
        {pct : Attendance percentage (100 = present, 0 = absent)}
        {--late : Flag the student as late}
        {--reason=Marked from CRM : Override reason for the audit trail}';

    protected $description = "Set a student's attendance for a class (creates the record if Zoom missed it)";

    public function handle(AuditLogger $audit): int
    {
        $session = $this->findSession((string) $this->argument('session'));

        if ($session === null) {
            $this->error("Session '{$this->argument('session')}' not found.");

            return self::FAILURE;
        }

        return $this->runForTenant($session->tenant_id, function () use ($session, $audit): int {
            $pct = max(0, min(100, (int) $this->argument('pct')));

            $attendance = Attendance::query()->firstOrCreate(
                ['live_session_id' => $session->id, 'user_id' => (int) $this->argument('user')],
                ['tenant_id' => $session->tenant_id],
            );

            $attendance->update([
                'attended_pct' => $pct,
                'is_late' => (bool) $this->option('late'),
                'override_reason' => (string) $this->option('reason'),
            ]);

            $audit->log(
                action: 'attendance.overridden',
                target: $attendance,
                metadata: ['attended_pct' => $pct, 'is_late' => (bool) $this->option('late'), 'reason' => (string) $this->option('reason'), 'via' => 'crm'],
            );

            $this->info("Attendance for student #{$attendance->user_id} in class #{$session->id} set to {$pct}%.");

            return self::SUCCESS;
        });
    }
}
