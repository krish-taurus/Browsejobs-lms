<?php

declare(strict_types=1);

use App\Enums\BatchType;
use App\Enums\LiveSessionStatus;
use App\Jobs\CreateZoomMeeting;
use App\Models\Course;
use App\Models\LiveSession;
use App\Models\Tenant;
use App\Models\User;
use App\Models\ZoomLicense;
use App\Support\Zoom\FakeZoomClient;
use App\Support\Zoom\ZoomClient;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    Queue::fake(); // scheduling dispatches CreateZoomMeeting; don't run it against real Zoom
    $this->seed(RolePermissionSeeder::class);
    $this->tenant = Tenant::factory()->create();
    $this->admin = User::factory()->for($this->tenant)->create(['user_type' => 'staff']);
    $this->admin->assignRole('admin');
    $this->trainer = User::factory()->for($this->tenant)->create(['user_type' => 'staff']);
    $this->trainer->assignRole('trainer');
});

it('lists licenses and assignable trainers/mentors', function () {
    ZoomLicense::factory()->for($this->tenant)->create(['label' => 'Room A']);
    Sanctum::actingAs($this->admin);

    $body = $this->getJson('/api/v1/admin/zoom-licenses')->assertOk();
    expect($body->json('data'))->toHaveCount(1)
        ->and(collect($body->json('assignable'))->pluck('name'))->toContain($this->trainer->name);
});

it('creates a license and allocates it to a mentor', function () {
    Sanctum::actingAs($this->admin);

    $this->postJson('/api/v1/admin/zoom-licenses', [
        'label' => 'Primary host', 'zoom_user_id' => 'host1@browsejobs.ai', 'mentor_id' => $this->trainer->id,
    ])->assertCreated();

    $license = ZoomLicense::withoutGlobalScopes()->firstOrFail();
    expect($license->zoom_user_id)->toBe('host1@browsejobs.ai')->and($license->mentor_id)->toBe($this->trainer->id);
});

it('stops a mentor from holding two licenses', function () {
    ZoomLicense::factory()->for($this->tenant)->create(['mentor_id' => $this->trainer->id]);
    Sanctum::actingAs($this->admin);

    $this->postJson('/api/v1/admin/zoom-licenses', [
        'label' => 'Second', 'zoom_user_id' => 'host2@browsejobs.ai', 'mentor_id' => $this->trainer->id,
    ])->assertStatus(422);
});

it('hosts the class under the batch trainer allocated license, recording on', function () {
    $fake = new FakeZoomClient;
    app()->instance(ZoomClient::class, $fake);

    $session = withinTenant($this->tenant, function () {
        $course = Course::query()->create(['code' => 'DA', 'name' => 'Data', 'slug' => 'da']);
        $batch = $course->batches()->create(['number' => 'DA-1', 'type' => BatchType::Paid->value, 'trainer_id' => $this->trainer->id]);
        ZoomLicense::factory()->for($this->tenant)->create(['mentor_id' => $this->trainer->id, 'zoom_user_id' => 'trainer-host@x.ai']);

        return LiveSession::query()->create([
            'batch_id' => $batch->id, 'title' => 'Class', 'scheduled_start' => now()->addDay(),
            'status' => LiveSessionStatus::Scheduled->value, 'auto_record' => true,
        ]);
    });

    (new CreateZoomMeeting($session->id))->handle($fake);

    // The meeting was created under the trainer's Zoom user, with cloud recording on.
    expect($fake->created)->toHaveCount(1)
        ->and($fake->created[0]['host'])->toBe('trainer-host@x.ai')
        ->and($fake->created[0]['auto_record'])->toBeTrue();
    expect($session->fresh()->zoom_meeting_id)->not->toBeNull();
});

it('falls back to the default host when the trainer has no license', function () {
    $fake = new FakeZoomClient;
    app()->instance(ZoomClient::class, $fake);

    $session = withinTenant($this->tenant, function () {
        $course = Course::query()->create(['code' => 'DA', 'name' => 'Data', 'slug' => 'da']);
        $batch = $course->batches()->create(['number' => 'DA-2', 'type' => BatchType::Paid->value, 'trainer_id' => $this->trainer->id]);

        return LiveSession::query()->create([
            'batch_id' => $batch->id, 'title' => 'Class', 'scheduled_start' => now()->addDay(),
            'status' => LiveSessionStatus::Scheduled->value, 'auto_record' => true,
        ]);
    });

    (new CreateZoomMeeting($session->id))->handle($fake);

    expect($fake->created[0]['host'])->toBeNull(); // "me" — the account's own user
});

it('respects a session scheduled with recording off', function () {
    $fake = new FakeZoomClient;
    app()->instance(ZoomClient::class, $fake);

    Sanctum::actingAs($this->admin);
    $batch = withinTenant($this->tenant, function () {
        $course = Course::query()->create(['code' => 'DA', 'name' => 'Data', 'slug' => 'da']);

        return $course->batches()->create(['number' => 'DA-3', 'type' => BatchType::Paid->value, 'trainer_id' => $this->trainer->id]);
    });

    $this->postJson("/api/v1/admin/batches/{$batch->id}/sessions", [
        'title' => 'Private session', 'scheduled_start' => now()->addDay()->toIso8601String(), 'record' => false,
    ])->assertCreated();

    $session = LiveSession::withoutGlobalScopes()->where('batch_id', $batch->id)->firstOrFail();
    expect($session->auto_record)->toBeFalse();

    (new CreateZoomMeeting($session->id))->handle($fake);
    expect($fake->created[0]['auto_record'])->toBeFalse();
});

it('defaults recording on when not specified', function () {
    Sanctum::actingAs($this->admin);
    $batch = withinTenant($this->tenant, function () {
        $course = Course::query()->create(['code' => 'DA', 'name' => 'Data', 'slug' => 'da']);

        return $course->batches()->create(['number' => 'DA-4', 'type' => BatchType::Paid->value]);
    });

    $this->postJson("/api/v1/admin/batches/{$batch->id}/sessions", [
        'title' => 'Normal class', 'scheduled_start' => now()->addDay()->toIso8601String(),
    ])->assertCreated();

    expect(LiveSession::withoutGlobalScopes()->where('batch_id', $batch->id)->value('auto_record'))->toBe(true);
});

it('denies a user without manage-batches', function () {
    Sanctum::actingAs($this->trainer); // trainer lacks manage-batches
    $this->getJson('/api/v1/admin/zoom-licenses')->assertForbidden();
});

it('404s another tenant license', function () {
    $other = Tenant::factory()->create();
    $theirs = ZoomLicense::factory()->for($other)->create();
    Sanctum::actingAs($this->admin);

    $this->putJson("/api/v1/admin/zoom-licenses/{$theirs->id}", [
        'label' => 'Hijack', 'zoom_user_id' => 'x@x.ai',
    ])->assertNotFound();
});
