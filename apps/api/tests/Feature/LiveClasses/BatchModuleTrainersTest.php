<?php

declare(strict_types=1);

use App\Actions\LiveClasses\RescheduleLiveSession;
use App\Enums\BatchType;
use App\Enums\LiveSessionStatus;
use App\Models\Course;
use App\Models\LiveSession;
use App\Models\Module;
use App\Models\Tenant;
use App\Models\Topic;
use App\Models\User;
use App\Support\Notifications\SessionNotifier;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;

use function Pest\Laravel\getJson;
use function Pest\Laravel\putJson;

beforeEach(function () {
    Queue::fake();
    $this->seed(RolePermissionSeeder::class);
    $this->tenant = Tenant::factory()->create();
    $this->admin = User::factory()->for($this->tenant)->create(['user_type' => 'staff']);
    $this->admin->assignRole('admin');
    $this->anil = User::factory()->for($this->tenant)->create(['user_type' => 'staff', 'name' => 'Anil']);
    $this->anil->assignRole('trainer');
    $this->bela = User::factory()->for($this->tenant)->create(['user_type' => 'staff', 'name' => 'Bela']);
    $this->bela->assignRole('trainer');

    [$this->batch, $this->python, $this->pyspark] = withinTenant($this->tenant, function () {
        $course = Course::query()->create(['code' => 'DE', 'name' => 'DE', 'slug' => 'de']);
        $batch = $course->batches()->create(['number' => 'DE-1', 'type' => BatchType::Paid->value, 'trainer_id' => $this->anil->id]);
        $python = Module::query()->create(['tenant_id' => $this->tenant->id, 'course_id' => $course->id, 'name' => 'Python', 'position' => 1]);
        $pyspark = Module::query()->create(['tenant_id' => $this->tenant->id, 'course_id' => $course->id, 'name' => 'PySpark', 'position' => 2]);

        return [$batch, $python, $pyspark];
    });
});

it('lists modules, the lead trainer, and available trainers', function () {
    Sanctum::actingAs($this->admin);

    getJson("/api/v1/admin/batches/{$this->batch->id}/module-trainers")
        ->assertOk()
        ->assertJsonPath('data.lead_trainer_id', $this->anil->id)
        ->assertJsonCount(2, 'data.modules')
        ->assertJsonCount(3, 'data.trainers'); // Anil + Bela + the admin (admins can teach too)
});

it('assigns a different trainer per module', function () {
    Sanctum::actingAs($this->admin);

    putJson("/api/v1/admin/batches/{$this->batch->id}/module-trainers", [
        'lead_trainer_id' => $this->anil->id,
        'assignments' => [
            ['module_id' => $this->python->id, 'trainer_id' => $this->anil->id],
            ['module_id' => $this->pyspark->id, 'trainer_id' => $this->bela->id],
        ],
    ])->assertOk();

    $batch = $this->batch->fresh()->load('moduleTrainers.trainer');
    expect($batch->trainerForModule($this->pyspark->id)->id)->toBe($this->bela->id)
        ->and($batch->trainerForModule($this->python->id)->id)->toBe($this->anil->id)
        // A module with no override falls back to the lead trainer.
        ->and($batch->trainerForModule(99999)->id)->toBe($this->anil->id);
});

it('notifies the module trainer — not the lead — when that module class reschedules', function () {
    withinTenant($this->tenant, fn () => $this->batch->moduleTrainers()->create([
        'tenant_id' => $this->tenant->id, 'module_id' => $this->pyspark->id, 'user_id' => $this->bela->id,
    ]));

    $session = withinTenant($this->tenant, function () {
        $topic = Topic::query()->create(['tenant_id' => $this->tenant->id, 'module_id' => $this->pyspark->id, 'name' => 'RDDs', 'position' => 1]);

        return LiveSession::query()->create([
            'batch_id' => $this->batch->id, 'topic_id' => $topic->id, 'title' => 'PySpark class',
            'scheduled_start' => now()->addDays(2), 'status' => LiveSessionStatus::Scheduled->value, 'reminder_token' => 't',
        ]);
    });

    $spy = Mockery::spy(SessionNotifier::class);
    app()->instance(SessionNotifier::class, $spy);

    withinTenant($this->tenant, fn () => app(RescheduleLiveSession::class)->handle($session, now()->addDays(3), now()->addDays(3)->addHour(), 'clash'));

    $spy->shouldHaveReceived('trainerRescheduled')->withArgs(fn (User $t) => $t->id === $this->bela->id)->once();
});

it('denies staff without manage-batches', function () {
    $support = User::factory()->for($this->tenant)->create(['user_type' => 'staff']);
    $support->assignRole('support-agent');
    Sanctum::actingAs($support);

    getJson("/api/v1/admin/batches/{$this->batch->id}/module-trainers")->assertForbidden();
});
