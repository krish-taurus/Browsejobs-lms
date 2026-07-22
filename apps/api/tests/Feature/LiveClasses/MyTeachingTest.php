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

use function Pest\Laravel\getJson;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
    $this->tenant = Tenant::factory()->create();

    $this->anil = User::factory()->for($this->tenant)->create(['user_type' => 'staff', 'name' => 'Anil']);
    $this->anil->assignRole('trainer');
    $this->bela = User::factory()->for($this->tenant)->create(['user_type' => 'staff', 'name' => 'Bela']);
    $this->bela->assignRole('trainer');
    $this->mira = User::factory()->for($this->tenant)->create(['user_type' => 'staff', 'name' => 'Mira']);
    $this->mira->assignRole('mentor');

    [$this->batch, $this->python, $this->pyspark] = withinTenant($this->tenant, function () {
        $course = Course::query()->create(['code' => 'DE', 'name' => 'Data Engineering', 'slug' => 'de']);
        $batch = $course->batches()->create(['number' => 'DE-1', 'type' => BatchType::Paid->value, 'trainer_id' => $this->anil->id]);
        $python = Module::query()->create(['tenant_id' => $this->tenant->id, 'course_id' => $course->id, 'name' => 'Python', 'position' => 1]);
        $pyspark = Module::query()->create(['tenant_id' => $this->tenant->id, 'course_id' => $course->id, 'name' => 'PySpark', 'position' => 2]);

        // Bela teaches the PySpark module; Mira mentors the batch.
        $batch->moduleTrainers()->create(['tenant_id' => $this->tenant->id, 'module_id' => $pyspark->id, 'user_id' => $this->bela->id]);
        $batch->batchMentors()->create(['tenant_id' => $this->tenant->id, 'user_id' => $this->mira->id]);

        return [$batch, $python, $pyspark];
    });
});

it('shows the lead trainer their batch, role and upcoming class they teach', function () {
    $session = withinTenant($this->tenant, function () {
        $topic = Topic::query()->create(['tenant_id' => $this->tenant->id, 'module_id' => $this->python->id, 'name' => 'Loops', 'position' => 1]);

        return LiveSession::query()->create([
            'batch_id' => $this->batch->id, 'topic_id' => $topic->id, 'title' => 'Python class',
            'scheduled_start' => now()->addDays(2), 'status' => LiveSessionStatus::Scheduled->value, 'reminder_token' => 't',
        ]);
    });

    Sanctum::actingAs($this->anil);

    getJson('/api/v1/admin/my-teaching')
        ->assertOk()
        ->assertJsonCount(1, 'data.batches')
        ->assertJsonPath('data.batches.0.number', 'DE-1')
        ->assertJsonPath('data.batches.0.roles.0', 'Lead trainer')
        ->assertJsonCount(1, 'data.upcoming')
        ->assertJsonPath('data.upcoming.0.id', $session->id)
        ->assertJsonPath('data.upcoming.0.you_teach', true); // Python module falls to the lead
});

it('shows a module trainer only the module they teach', function () {
    Sanctum::actingAs($this->bela);

    getJson('/api/v1/admin/my-teaching')
        ->assertOk()
        ->assertJsonCount(1, 'data.batches')
        ->assertJsonPath('data.batches.0.roles.0', 'Module trainer')
        ->assertJsonPath('data.batches.0.modules.0', 'PySpark');
});

it('marks a class the viewer does not personally teach', function () {
    withinTenant($this->tenant, function () {
        $topic = Topic::query()->create(['tenant_id' => $this->tenant->id, 'module_id' => $this->pyspark->id, 'name' => 'RDDs', 'position' => 1]);

        LiveSession::query()->create([
            'batch_id' => $this->batch->id, 'topic_id' => $topic->id, 'title' => 'PySpark class',
            'scheduled_start' => now()->addDays(2), 'status' => LiveSessionStatus::Scheduled->value, 'reminder_token' => 't',
        ]);
    });

    // Anil is the lead but PySpark is Bela's module, so Anil does not teach it.
    Sanctum::actingAs($this->anil);

    getJson('/api/v1/admin/my-teaching')
        ->assertOk()
        ->assertJsonPath('data.upcoming.0.you_teach', false);
});

it('shows a mentor the batch they mentor', function () {
    Sanctum::actingAs($this->mira);

    getJson('/api/v1/admin/my-teaching')
        ->assertOk()
        ->assertJsonCount(1, 'data.batches')
        ->assertJsonPath('data.batches.0.roles.0', 'Mentor');
});

it('does not leak another tenant\'s batches', function () {
    $otherTenant = Tenant::factory()->create();
    $intruder = User::factory()->for($otherTenant)->create(['user_type' => 'staff']);
    $intruder->assignRole('trainer');

    Sanctum::actingAs($intruder);

    getJson('/api/v1/admin/my-teaching')
        ->assertOk()
        ->assertJsonCount(0, 'data.batches');
});

it('shows an empty board to staff with no allocations', function () {
    $support = User::factory()->for($this->tenant)->create(['user_type' => 'staff']);
    $support->assignRole('support-agent');
    Sanctum::actingAs($support);

    getJson('/api/v1/admin/my-teaching')
        ->assertOk()
        ->assertJsonCount(0, 'data.batches')
        ->assertJsonCount(0, 'data.upcoming');
});
