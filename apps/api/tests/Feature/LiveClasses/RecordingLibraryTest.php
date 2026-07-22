<?php

declare(strict_types=1);

use App\Enums\BatchType;
use App\Enums\LiveSessionStatus;
use App\Enums\RecordingStatus;
use App\Models\Course;
use App\Models\LiveSession;
use App\Models\Module;
use App\Models\Recording;
use App\Models\Tenant;
use App\Models\Topic;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Laravel\Sanctum\Sanctum;

use function Pest\Laravel\getJson;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
    $this->tenant = Tenant::factory()->create();

    $this->anil = User::factory()->for($this->tenant)->create(['user_type' => 'staff', 'name' => 'Anil']);
    $this->anil->assignRole('trainer');
    $this->bela = User::factory()->for($this->tenant)->create(['user_type' => 'staff', 'name' => 'Bela']);
    $this->bela->assignRole('trainer');

    withinTenant($this->tenant, function () {
        $course = Course::query()->create(['code' => 'DE', 'name' => 'Data Engineering', 'slug' => 'de']);
        $batch = $course->batches()->create(['number' => 'DE-1', 'type' => BatchType::Paid->value, 'trainer_id' => $this->anil->id]);
        $pyspark = Module::query()->create(['tenant_id' => $this->tenant->id, 'course_id' => $course->id, 'name' => 'PySpark', 'position' => 1]);
        $batch->moduleTrainers()->create(['tenant_id' => $this->tenant->id, 'module_id' => $pyspark->id, 'user_id' => $this->bela->id]);
        $topic = Topic::query()->create(['tenant_id' => $this->tenant->id, 'module_id' => $pyspark->id, 'name' => 'RDDs', 'position' => 1]);

        $session = LiveSession::query()->create([
            'batch_id' => $batch->id, 'topic_id' => $topic->id, 'title' => 'PySpark class',
            'scheduled_start' => now()->subDay(), 'status' => LiveSessionStatus::Ended->value, 'reminder_token' => 't',
        ]);
        Recording::query()->create([
            'live_session_id' => $session->id, 'title' => 'PySpark class',
            'play_url' => 'https://zoom.test/play/1', 'passcode' => 'pw1',
            'duration_seconds' => 3600, 'status' => RecordingStatus::Stored->value,
        ]);
    });
});

it('groups recordings by batch with class, date, trainer and watch link', function () {
    Sanctum::actingAs($this->anil);

    getJson('/api/v1/admin/recordings')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.batch.number', 'DE-1')
        ->assertJsonPath('data.0.batch.course_code', 'DE')
        ->assertJsonCount(1, 'data.0.recordings')
        ->assertJsonPath('data.0.recordings.0.session_title', 'PySpark class')
        ->assertJsonPath('data.0.recordings.0.trainer', 'Bela') // module trainer, not the lead
        ->assertJsonPath('data.0.recordings.0.watch_url', 'https://zoom.test/play/1')
        ->assertJsonPath('data.0.recordings.0.passcode', 'pw1');
});

it('does not list another tenant\'s recordings', function () {
    $otherTenant = Tenant::factory()->create();
    $intruder = User::factory()->for($otherTenant)->create(['user_type' => 'staff']);
    $intruder->assignRole('trainer');

    Sanctum::actingAs($intruder);

    getJson('/api/v1/admin/recordings')->assertOk()->assertJsonCount(0, 'data');
});

it('denies staff without teach-classes', function () {
    $support = User::factory()->for($this->tenant)->create(['user_type' => 'staff']);
    $support->assignRole('support-agent');
    Sanctum::actingAs($support);

    getJson('/api/v1/admin/recordings')->assertForbidden();
});
