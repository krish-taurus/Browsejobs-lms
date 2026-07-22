<?php

declare(strict_types=1);

use App\Enums\BatchType;
use App\Enums\LiveSessionStatus;
use App\Models\Course;
use App\Models\LiveSession;
use App\Models\Module;
use App\Models\Tenant;
use App\Models\Topic;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Laravel\Sanctum\Sanctum;

use function Pest\Laravel\postJson;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
    $this->tenant = Tenant::factory()->create();

    $this->admin = User::factory()->for($this->tenant)->create(['user_type' => 'staff']);
    $this->admin->assignRole('admin');
    $this->anil = User::factory()->for($this->tenant)->create(['user_type' => 'staff', 'name' => 'Anil']);
    $this->anil->assignRole('trainer');
    $this->bela = User::factory()->for($this->tenant)->create(['user_type' => 'staff', 'name' => 'Bela']);
    $this->bela->assignRole('trainer');

    [$this->batch, $this->pyspark, $this->session] = withinTenant($this->tenant, function () {
        $course = Course::query()->create(['code' => 'DE', 'name' => 'DE', 'slug' => 'de']);
        $batch = $course->batches()->create(['number' => 'DE-1', 'type' => BatchType::Paid->value, 'trainer_id' => $this->anil->id]);
        $pyspark = Module::query()->create(['tenant_id' => $this->tenant->id, 'course_id' => $course->id, 'name' => 'PySpark', 'position' => 1]);
        $topic = Topic::query()->create(['tenant_id' => $this->tenant->id, 'module_id' => $pyspark->id, 'name' => 'RDDs', 'position' => 1]);

        // Bela teaches the PySpark module.
        $batch->moduleTrainers()->create(['tenant_id' => $this->tenant->id, 'module_id' => $pyspark->id, 'user_id' => $this->bela->id]);

        $session = LiveSession::query()->create([
            'batch_id' => $batch->id, 'topic_id' => $topic->id, 'title' => 'PySpark class',
            'scheduled_start' => now(), 'scheduled_end' => now()->addHour(),
            'status' => LiveSessionStatus::Scheduled->value, 'reminder_token' => 't',
            'zoom_join_url' => 'https://zoom.test/j/1', 'zoom_start_url' => 'https://zoom.test/s/HOST',
        ]);

        return [$batch, $pyspark, $session];
    });
});

it('hands the assigned module trainer the host start url', function () {
    Sanctum::actingAs($this->bela);

    postJson("/api/v1/admin/sessions/{$this->session->id}/start")
        ->assertOk()->assertJsonPath('data.start_url', 'https://zoom.test/s/HOST');
});

it('lets an admin start any class', function () {
    Sanctum::actingAs($this->admin);

    postJson("/api/v1/admin/sessions/{$this->session->id}/start")
        ->assertOk()->assertJsonPath('data.start_url', 'https://zoom.test/s/HOST');
});

it('refuses a trainer who is not assigned to the class', function () {
    // Anil is the batch lead, but PySpark is Bela's module — so Anil is not this class's host.
    Sanctum::actingAs($this->anil);

    postJson("/api/v1/admin/sessions/{$this->session->id}/start")->assertStatus(422);
});

it('lets the lead trainer host a class with no module override', function () {
    $session = withinTenant($this->tenant, fn () => LiveSession::query()->create([
        'batch_id' => $this->batch->id, 'title' => 'General class',
        'scheduled_start' => now(), 'scheduled_end' => now()->addHour(),
        'status' => LiveSessionStatus::Scheduled->value, 'reminder_token' => 't2',
        'zoom_start_url' => 'https://zoom.test/s/LEAD',
    ]));

    Sanctum::actingAs($this->anil);

    postJson("/api/v1/admin/sessions/{$session->id}/start")
        ->assertOk()->assertJsonPath('data.start_url', 'https://zoom.test/s/LEAD');
});

it('refuses to start too early', function () {
    $future = withinTenant($this->tenant, fn () => LiveSession::query()->create([
        'batch_id' => $this->batch->id, 'topic_id' => $this->session->topic_id, 'title' => 'Later',
        'scheduled_start' => now()->addHours(3), 'status' => LiveSessionStatus::Scheduled->value,
        'reminder_token' => 't3', 'zoom_start_url' => 'https://zoom.test/s/LATER',
    ]));

    Sanctum::actingAs($this->bela);

    postJson("/api/v1/admin/sessions/{$future->id}/start")->assertStatus(422);
});

it('refuses when the meeting is not ready', function () {
    withinTenant($this->tenant, fn () => $this->session->update(['zoom_start_url' => null]));

    Sanctum::actingAs($this->bela);

    postJson("/api/v1/admin/sessions/{$this->session->id}/start")->assertStatus(422);
});

it('refuses to start a cancelled class', function () {
    withinTenant($this->tenant, fn () => $this->session->update(['status' => LiveSessionStatus::Cancelled->value]));

    Sanctum::actingAs($this->bela);

    postJson("/api/v1/admin/sessions/{$this->session->id}/start")->assertStatus(422);
});

it('does not expose another tenant\'s class', function () {
    $otherTenant = Tenant::factory()->create();
    $intruder = User::factory()->for($otherTenant)->create(['user_type' => 'staff']);
    $intruder->assignRole('admin');

    Sanctum::actingAs($intruder);

    postJson("/api/v1/admin/sessions/{$this->session->id}/start")->assertNotFound();
});
