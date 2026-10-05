<?php

declare(strict_types=1);

use App\Enums\BatchType;
use App\Enums\LiveSessionStatus;
use App\Models\Course;
use App\Models\LiveSession;
use App\Models\Tenant;

/**
 * Without this, a class stayed "scheduled" for ever: finished classes sat in the
 * student's Upcoming list and the recording sync skipped them.
 */
beforeEach(function () {
    $this->tenant = Tenant::factory()->create();

    $this->batch = withinTenant($this->tenant, function () {
        $course = Course::query()->create(['code' => 'DE', 'name' => 'Data Eng', 'slug' => 'de']);

        return $course->batches()->create(['number' => 'DE-202608-90', 'type' => BatchType::Paid->value]);
    });
});

function sessionAt(int $batchId, string $start, ?string $end = null): LiveSession
{
    return LiveSession::query()->withoutGlobalScopes()->create([
        'tenant_id' => 1,
        'batch_id' => $batchId,
        'title' => 'Class',
        'scheduled_start' => $start,
        'scheduled_end' => $end,
        'status' => LiveSessionStatus::Scheduled->value,
        'reminder_token' => 'tok'.uniqid(),
    ]);
}

it('closes a class whose slot finished more than an hour ago', function () {
    $past = sessionAt($this->batch->id, now()->subHours(4)->toDateTimeString(), now()->subHours(3)->toDateTimeString());

    $this->artisan('classes:close-finished')->assertExitCode(0);

    expect($past->fresh()->status)->toBe(LiveSessionStatus::Ended);
});

it('leaves a class that has only just over-run', function () {
    // Ended 10 minutes ago — trainers over-run, so it must not close yet.
    $running = sessionAt($this->batch->id, now()->subHours(2)->toDateTimeString(), now()->subMinutes(10)->toDateTimeString());

    $this->artisan('classes:close-finished')->assertExitCode(0);

    expect($running->fresh()->status)->toBe(LiveSessionStatus::Scheduled);
});

it('never touches a future class', function () {
    $future = sessionAt($this->batch->id, now()->addDay()->toDateTimeString());

    $this->artisan('classes:close-finished')->assertExitCode(0);

    expect($future->fresh()->status)->toBe(LiveSessionStatus::Scheduled);
});

it('leaves a cancelled class cancelled', function () {
    $cancelled = sessionAt($this->batch->id, now()->subDays(2)->toDateTimeString(), now()->subDays(2)->addHour()->toDateTimeString());
    $cancelled->forceFill(['status' => LiveSessionStatus::Cancelled->value])->save();

    $this->artisan('classes:close-finished')->assertExitCode(0);

    expect($cancelled->fresh()->status)->toBe(LiveSessionStatus::Cancelled);
});

it('changes nothing on a dry run', function () {
    $past = sessionAt($this->batch->id, now()->subHours(5)->toDateTimeString(), now()->subHours(4)->toDateTimeString());

    $this->artisan('classes:close-finished', ['--dry-run' => true])->assertExitCode(0);

    expect($past->fresh()->status)->toBe(LiveSessionStatus::Scheduled);
});
