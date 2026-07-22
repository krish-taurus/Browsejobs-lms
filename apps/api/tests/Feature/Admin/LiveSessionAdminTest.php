<?php

declare(strict_types=1);

use App\Enums\BatchType;
use App\Enums\LiveSessionStatus;
use App\Jobs\CreateZoomMeeting;
use App\Models\Batch;
use App\Models\BatchMember;
use App\Models\Course;
use App\Models\LiveSession;
use App\Models\SessionChange;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Notifications\SessionNotifier;
use Carbon\CarbonInterface;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    Queue::fake(); // don't dispatch real Zoom jobs
    $this->seed(RolePermissionSeeder::class);
    $this->tenant = Tenant::factory()->create();
    $this->admin = User::factory()->for($this->tenant)->create(['user_type' => 'staff']);
    $this->admin->assignRole('admin');
    $this->student = User::factory()->for($this->tenant)->create(['user_type' => 'student']);

    [$this->batch, $this->session] = withinTenant($this->tenant, function () {
        $course = Course::query()->create(['code' => 'DA', 'name' => 'Data Analytics', 'slug' => 'da']);
        $batch = $course->batches()->create(['number' => 'DA-202608-01', 'type' => BatchType::Paid->value]);
        BatchMember::query()->create(['batch_id' => $batch->id, 'user_id' => $this->student->id, 'status' => 'enrolled']);
        $session = LiveSession::query()->create([
            'batch_id' => $batch->id, 'title' => 'Window functions',
            'scheduled_start' => now()->addDays(2), 'status' => LiveSessionStatus::Scheduled->value,
            'zoom_meeting_id' => '900000001', 'reminder_token' => 'original',
        ]);

        return [$batch, $session];
    });

    // A spy that records who was notified — this is the "message goes out to the batch"
    // guarantee the feature is about.
    $this->notified = collect();
    $spy = $this->notified;
    app()->instance(SessionNotifier::class, new class($spy) implements SessionNotifier
    {
        public function __construct(private $sink) {}

        public function reminder(User $s, LiveSession $ls, string $url, string $w): void {}

        public function cancelled(User $s, LiveSession $ls, string $r): void
        {
            $this->sink->push(['event' => 'cancelled', 'student' => $s->id, 'reason' => $r]);
        }

        public function rescheduled(User $s, LiveSession $ls, CarbonInterface $p, string $r): void
        {
            $this->sink->push(['event' => 'rescheduled', 'student' => $s->id, 'reason' => $r]);
        }

        public function trainerCancelled(User $t, LiveSession $ls, string $r): void {}

        public function trainerRescheduled(User $t, LiveSession $ls, CarbonInterface $p, string $r): void {}
    });
});

it('schedules a class on a batch', function () {
    Sanctum::actingAs($this->admin);

    $this->postJson("/api/v1/admin/batches/{$this->batch->id}/sessions", [
        'title' => 'Extra revision session',
        'scheduled_start' => now()->addDays(3)->toIso8601String(),
    ])->assertCreated()->assertJsonPath('data.title', 'Extra revision session')
        ->assertJsonPath('data.status', 'scheduled');

    expect(LiveSession::withoutGlobalScopes()->where('title', 'Extra revision session')->exists())->toBeTrue();
});

it('lists a batch classes newest first', function () {
    Sanctum::actingAs($this->admin);

    $this->getJson("/api/v1/admin/batches/{$this->batch->id}/sessions")
        ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.title', 'Window functions');
});

it('repairs a class with no meeting by dispatching meeting creation', function () {
    Sanctum::actingAs($this->admin);

    // The seeded class already has a zoom_meeting_id — clear it so it needs repair.
    withinTenant($this->tenant, fn () => $this->session->update(['zoom_meeting_id' => null]));

    $this->postJson("/api/v1/admin/sessions/{$this->session->id}/create-meeting")
        ->assertStatus(202)->assertJsonPath('data.status', 'creating');

    Queue::assertPushed(CreateZoomMeeting::class, fn ($job) => $job->liveSessionId === $this->session->id);
});

it('does not recreate a meeting that already exists', function () {
    Sanctum::actingAs($this->admin);

    $this->postJson("/api/v1/admin/sessions/{$this->session->id}/create-meeting")
        ->assertOk()->assertJsonPath('data.status', 'exists');

    Queue::assertNotPushed(CreateZoomMeeting::class);
});

it('flags a class as startable for an admin only inside the host window with a meeting', function () {
    Sanctum::actingAs($this->admin);

    // The seeded class is 2 days out with no host URL — not startable.
    $this->getJson("/api/v1/admin/batches/{$this->batch->id}/sessions")
        ->assertOk()->assertJsonPath('data.0.can_start', false);

    // A class starting now, with a host URL, is startable for the admin.
    withinTenant($this->tenant, fn () => $this->session->update([
        'scheduled_start' => now(), 'scheduled_end' => now()->addHour(),
        'zoom_start_url' => 'https://zoom.test/s/HOST',
    ]));

    $this->getJson("/api/v1/admin/batches/{$this->batch->id}/sessions")
        ->assertOk()->assertJsonPath('data.0.can_start', true);
});

it('reschedules a class and notifies the whole batch', function () {
    Sanctum::actingAs($this->admin);

    $this->postJson("/api/v1/admin/sessions/{$this->session->id}/reschedule", [
        'scheduled_start' => now()->addDays(4)->toIso8601String(),
        'reason' => 'Trainer travelling',
    ])->assertOk();

    expect($this->session->fresh()->scheduled_start->toDateString())->toBe(now()->addDays(4)->toDateString())
        ->and(SessionChange::withoutGlobalScopes()->where('type', 'rescheduled')->count())->toBe(1);

    // The student was told, with the reason.
    expect($this->notified)->toHaveCount(1)
        ->and($this->notified->first()['event'])->toBe('rescheduled')
        ->and($this->notified->first()['student'])->toBe($this->student->id)
        ->and($this->notified->first()['reason'])->toBe('Trainer travelling');
});

it('cancels a class and notifies the whole batch', function () {
    Sanctum::actingAs($this->admin);

    $this->postJson("/api/v1/admin/sessions/{$this->session->id}/cancel", [
        'reason' => 'Public holiday',
    ])->assertOk();

    expect($this->session->fresh()->status)->toBe(LiveSessionStatus::Cancelled)
        ->and(SessionChange::withoutGlobalScopes()->where('type', 'cancelled')->count())->toBe(1)
        ->and($this->notified)->toHaveCount(1)
        ->and($this->notified->first()['event'])->toBe('cancelled')
        ->and($this->notified->first()['reason'])->toBe('Public holiday');
});

it('refuses to reschedule an already-cancelled class', function () {
    withinTenant($this->tenant, fn () => $this->session->update(['status' => LiveSessionStatus::Cancelled->value]));
    Sanctum::actingAs($this->admin);

    // No point notifying a batch about a class that was already called off.
    $this->postJson("/api/v1/admin/sessions/{$this->session->id}/reschedule", [
        'scheduled_start' => now()->addDays(4)->toIso8601String(), 'reason' => 'Oops',
    ])->assertStatus(409);

    expect($this->notified)->toHaveCount(0);
});

it('validates the new time is in the future', function () {
    Sanctum::actingAs($this->admin);

    $this->postJson("/api/v1/admin/sessions/{$this->session->id}/reschedule", [
        'scheduled_start' => now()->subDay()->toIso8601String(), 'reason' => 'Backwards',
    ])->assertStatus(422);
});

it('requires teach-classes permission', function () {
    $mentor = User::factory()->for($this->tenant)->create(['user_type' => 'staff']);
    $mentor->assignRole('mentor'); // has mentor-students, not teach-classes
    Sanctum::actingAs($mentor);

    $this->getJson("/api/v1/admin/batches/{$this->batch->id}/sessions")->assertForbidden();
    $this->postJson("/api/v1/admin/batches/{$this->batch->id}/sessions", [
        'title' => 'X', 'scheduled_start' => now()->addDay()->toIso8601String(),
    ])->assertForbidden();
});

it('grants class scheduling to trainers', function () {
    $trainer = User::factory()->for($this->tenant)->create(['user_type' => 'staff']);
    $trainer->assignRole('trainer');
    Sanctum::actingAs($trainer);

    $this->getJson("/api/v1/admin/batches/{$this->batch->id}/sessions")->assertOk();
});

it('404s a class in another tenant', function () {
    $other = Tenant::factory()->create();
    $theirs = withinTenant($other, function () use ($other) {
        $course = Course::query()->create(['code' => 'XX', 'name' => 'X', 'slug' => 'x', 'tenant_id' => $other->id]);
        $batch = $course->batches()->create(['number' => 'XX-1', 'type' => BatchType::Paid->value, 'tenant_id' => $other->id]);

        return LiveSession::query()->create([
            'tenant_id' => $other->id, 'batch_id' => $batch->id, 'title' => 'Theirs',
            'scheduled_start' => now()->addDay(), 'status' => LiveSessionStatus::Scheduled->value,
        ]);
    });

    Sanctum::actingAs($this->admin);
    $this->postJson("/api/v1/admin/sessions/{$theirs->id}/cancel", ['reason' => 'x'])->assertNotFound();
});
