<?php

declare(strict_types=1);

use App\Actions\LiveClasses\JoinLiveSession;
use App\Enums\BatchMemberStatus;
use App\Enums\BatchType;
use App\Models\Batch;
use App\Models\BatchMember;
use App\Models\Course;
use App\Models\LiveSession;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Fees\FeeGate;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    $this->tenant = Tenant::factory()->create();

    [$this->batch, $this->session] = withinTenant($this->tenant, function () {
        $course = Course::query()->create(['code' => 'DE', 'name' => 'Data Eng', 'slug' => 'de']);
        $batch = $course->batches()->create(['number' => 'DE-202608-01', 'type' => BatchType::Paid->value]);
        $session = LiveSession::query()->create([
            'batch_id' => $batch->id, 'title' => 'Class', 'scheduled_start' => now(),
            'zoom_meeting_id' => '900000001', 'zoom_join_url' => 'https://zoom.test/j/900000001',
        ]);

        return [$batch, $session];
    });

    $this->student = User::factory()->for($this->tenant)->create(['user_type' => 'student']);
});

function enrol(int $batchId, int $userId, int $tenantId, BatchMemberStatus $status = BatchMemberStatus::Enrolled): void
{
    BatchMember::query()->create([
        'tenant_id' => $tenantId, 'batch_id' => $batchId, 'user_id' => $userId, 'status' => $status->value,
    ]);
}

it('returns the join url to an enrolled student who passes the fee gate', function () {
    enrol($this->batch->id, $this->student->id, $this->tenant->id);

    $url = app(JoinLiveSession::class)->handle($this->session, $this->student);

    expect($url)->toBe('https://zoom.test/j/900000001');
});

it('rejects a student who is not in the batch', function () {
    expect(fn () => app(JoinLiveSession::class)->handle($this->session, $this->student))
        ->toThrow(ValidationException::class);
});

it('rejects a dropped member', function () {
    enrol($this->batch->id, $this->student->id, $this->tenant->id, BatchMemberStatus::Dropped);

    expect(fn () => app(JoinLiveSession::class)->handle($this->session, $this->student))
        ->toThrow(ValidationException::class);
});

it('refuses to hand out the link before the join window opens', function () {
    enrol($this->batch->id, $this->student->id, $this->tenant->id);

    withinTenant($this->tenant, function (): void {
        $this->session->update(['scheduled_start' => now()->addHours(3)]);
    });

    expect(fn () => app(JoinLiveSession::class)->handle($this->session->fresh(), $this->student))
        ->toThrow(ValidationException::class, 'Join opens 10 minutes before the class starts');
});

it('lets the student in within the pre-class window', function () {
    enrol($this->batch->id, $this->student->id, $this->tenant->id);

    withinTenant($this->tenant, function (): void {
        $this->session->update(['scheduled_start' => now()->addMinutes(8)]);
    });

    expect(app(JoinLiveSession::class)->handle($this->session->fresh(), $this->student))
        ->toBe('https://zoom.test/j/900000001');
});

it('points to the recording once the class has ended', function () {
    enrol($this->batch->id, $this->student->id, $this->tenant->id);

    withinTenant($this->tenant, function (): void {
        $this->session->update([
            'scheduled_start' => now()->subHours(3),
            'scheduled_end' => now()->subHours(2),
        ]);
    });

    expect(fn () => app(JoinLiveSession::class)->handle($this->session->fresh(), $this->student))
        ->toThrow(ValidationException::class, 'This class has ended');
});

it('blocks access when the fee gate denies it', function () {
    enrol($this->batch->id, $this->student->id, $this->tenant->id);

    app()->instance(FeeGate::class, new class implements FeeGate
    {
        public function allowsLiveAccess(User $student, Batch $batch): bool
        {
            return false;
        }
    });

    expect(fn () => app(JoinLiveSession::class)->handle($this->session, $this->student))
        ->toThrow(ValidationException::class);
});
