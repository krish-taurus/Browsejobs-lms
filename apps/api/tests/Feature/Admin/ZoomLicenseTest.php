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
use Illuminate\Support\Carbon;
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

/** A batch and a scheduled one-hour session on it at the given start. */
function rotationSession(Tenant $tenant, string $number, ?string $start = null): LiveSession
{
    return withinTenant($tenant, function () use ($number, $start) {
        $course = Course::query()->firstOrCreate(['slug' => 'da'], ['code' => 'DA', 'name' => 'Data']);
        $batch = $course->batches()->create(['number' => $number, 'type' => BatchType::Paid->value]);

        return LiveSession::query()->create([
            'batch_id' => $batch->id, 'title' => "Class {$number}",
            'scheduled_start' => $start !== null ? Carbon::parse($start) : now()->addDay(),
            'status' => LiveSessionStatus::Scheduled->value, 'auto_record' => true,
        ]);
    });
}

it('lists licenses', function () {
    ZoomLicense::factory()->for($this->tenant)->create(['label' => 'Room A']);
    Sanctum::actingAs($this->admin);

    $body = $this->getJson('/api/v1/admin/zoom-licenses')->assertOk();
    expect($body->json('data'))->toHaveCount(1)
        ->and($body->json('data.0.label'))->toBe('Room A');
});

it('creates a license', function () {
    Sanctum::actingAs($this->admin);

    $this->postJson('/api/v1/admin/zoom-licenses', [
        'label' => 'Primary host', 'zoom_user_id' => 'host1@browsejobs.ai',
    ])->assertCreated();

    expect(ZoomLicense::withoutGlobalScopes()->sole()->zoom_user_id)->toBe('host1@browsejobs.ai');
});

it('rotates: two overlapping classes claim different licenses, whoever teaches them', function () {
    $fake = new FakeZoomClient;
    app()->instance(ZoomClient::class, $fake);

    ZoomLicense::factory()->for($this->tenant)->create(['zoom_user_id' => 'host1@x.ai']);
    ZoomLicense::factory()->for($this->tenant)->create(['zoom_user_id' => 'host2@x.ai']);

    $start = now()->addDay()->toIso8601String();
    $first = rotationSession($this->tenant, 'DA-1', $start);
    $second = rotationSession($this->tenant, 'DA-2', $start);

    (new CreateZoomMeeting($first->id))->handle($fake);
    (new CreateZoomMeeting($second->id))->handle($fake);

    expect($fake->created[0]['host'])->toBe('host1@x.ai')
        ->and($fake->created[1]['host'])->toBe('host2@x.ai')
        ->and($fake->created[0]['auto_record'])->toBeTrue()
        ->and($first->fresh()->zoom_license_id)->not->toBeNull()
        ->and($second->fresh()->zoom_license_id)->not->toBe($first->fresh()->zoom_license_id);
});

it('reuses a license for a non-overlapping later class instead of dedicating it', function () {
    $fake = new FakeZoomClient;
    app()->instance(ZoomClient::class, $fake);

    ZoomLicense::factory()->for($this->tenant)->create(['zoom_user_id' => 'host1@x.ai']);

    $morning = rotationSession($this->tenant, 'DA-1', now()->addDay()->setTime(10, 0)->toIso8601String());
    $evening = rotationSession($this->tenant, 'DA-2', now()->addDay()->setTime(18, 0)->toIso8601String());

    (new CreateZoomMeeting($morning->id))->handle($fake);
    (new CreateZoomMeeting($evening->id))->handle($fake);

    // Same single license serves both — it is free again by evening.
    expect($fake->created[0]['host'])->toBe('host1@x.ai')
        ->and($fake->created[1]['host'])->toBe('host1@x.ai');
});

it('falls back to the default host when every license is busy at that time', function () {
    $fake = new FakeZoomClient;
    app()->instance(ZoomClient::class, $fake);

    ZoomLicense::factory()->for($this->tenant)->create(['zoom_user_id' => 'host1@x.ai']);

    $start = now()->addDay()->toIso8601String();
    $first = rotationSession($this->tenant, 'DA-1', $start);
    $second = rotationSession($this->tenant, 'DA-2', $start);

    (new CreateZoomMeeting($first->id))->handle($fake);
    (new CreateZoomMeeting($second->id))->handle($fake);

    expect($fake->created[0]['host'])->toBe('host1@x.ai')
        ->and($fake->created[1]['host'])->toBeNull() // "me" — the account's own user
        ->and($second->fresh()->zoom_license_id)->toBeNull();
});

it('frees a cancelled class\'s license for the next class at that time', function () {
    $fake = new FakeZoomClient;
    app()->instance(ZoomClient::class, $fake);

    ZoomLicense::factory()->for($this->tenant)->create(['zoom_user_id' => 'host1@x.ai']);

    $start = now()->addDay()->toIso8601String();
    $first = rotationSession($this->tenant, 'DA-1', $start);
    (new CreateZoomMeeting($first->id))->handle($fake);
    $first->update(['status' => LiveSessionStatus::Cancelled->value]);

    $second = rotationSession($this->tenant, 'DA-2', $start);
    (new CreateZoomMeeting($second->id))->handle($fake);

    expect($fake->created[1]['host'])->toBe('host1@x.ai');
});

it('falls back to the default host when there are no licenses at all', function () {
    $fake = new FakeZoomClient;
    app()->instance(ZoomClient::class, $fake);

    $session = rotationSession($this->tenant, 'DA-2');
    (new CreateZoomMeeting($session->id))->handle($fake);

    expect($fake->created[0]['host'])->toBeNull(); // "me" — the account's own user
});

it('never claims another tenant\'s license', function () {
    $fake = new FakeZoomClient;
    app()->instance(ZoomClient::class, $fake);

    $other = Tenant::factory()->create();
    ZoomLicense::factory()->for($other)->create(['zoom_user_id' => 'theirs@x.ai']);

    $session = rotationSession($this->tenant, 'DA-1');
    (new CreateZoomMeeting($session->id))->handle($fake);

    expect($fake->created[0]['host'])->toBeNull()
        ->and($session->fresh()->zoom_license_id)->toBeNull();
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
