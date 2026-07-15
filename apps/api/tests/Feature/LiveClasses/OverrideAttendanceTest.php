<?php

declare(strict_types=1);

use App\Actions\LiveClasses\OverrideAttendance;
use App\Enums\BatchType;
use App\Models\Attendance;
use App\Models\AuditLog;
use App\Models\Course;
use App\Models\LiveSession;
use App\Models\Tenant;
use App\Models\User;

it('lets a trainer override attendance and audits the change', function () {
    $tenant = Tenant::factory()->create();
    $student = User::factory()->for($tenant)->create(['user_type' => 'student']);
    $trainer = User::factory()->for($tenant)->create(['user_type' => 'staff']);

    $attendance = withinTenant($tenant, function () use ($student) {
        $course = Course::query()->create(['code' => 'DE', 'name' => 'Data Eng', 'slug' => 'de']);
        $batch = $course->batches()->create(['number' => 'DE-202608-01', 'type' => BatchType::Paid->value]);
        $session = LiveSession::query()->create([
            'batch_id' => $batch->id, 'title' => 'Class', 'scheduled_start' => now(),
        ]);

        return Attendance::query()->create([
            'live_session_id' => $session->id, 'user_id' => $student->id,
            'total_seconds' => 0, 'attended_pct' => 0, 'is_late' => true,
        ]);
    });

    withinTenant($tenant, function () use ($attendance, $trainer) {
        app(OverrideAttendance::class)->handle($attendance, 100, false, 'Zoom missed the join', $trainer);
    });

    $attendance->refresh();

    expect($attendance->attended_pct)->toBe(100)
        ->and($attendance->is_late)->toBeFalse()
        ->and($attendance->override_by)->toBe($trainer->id)
        ->and(AuditLog::withoutGlobalScopes()->where('action', 'attendance.overridden')->count())->toBe(1);
});
