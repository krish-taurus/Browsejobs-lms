<?php

declare(strict_types=1);

use App\Events\MockCompleted;
use App\Events\ModuleCompleted;
use App\Models\Course;
use App\Models\InAppNotification;
use App\Models\MockBlueprint;
use App\Models\MockInterview;
use App\Models\Module;
use App\Models\ModuleMockRequirement;
use App\Models\Tenant;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

use function Pest\Laravel\getJson;

beforeEach(function () {
    $this->tenant = Tenant::factory()->create();
    $this->student = User::factory()->for($this->tenant)->create(['user_type' => 'student']);
});

function aModule(Tenant $tenant, ?int $requiredMocks = null): Module
{
    return withinTenant($tenant, function () use ($tenant, $requiredMocks) {
        $course = Course::factory()->for($tenant)->create();

        return Module::query()->create([
            'tenant_id' => $tenant->id, 'course_id' => $course->id,
            'name' => 'ETL Foundations', 'position' => 0, 'required_mocks' => $requiredMocks,
        ]);
    });
}

/** Complete a mock as this student (fires the same event the real finish path fires). */
function finishAMock(User $student): void
{
    $interview = withinTenant($student->tenant, function () use ($student) {
        $course = Course::factory()->for($student->tenant)->create();
        $blueprint = MockBlueprint::query()->create([
            'tenant_id' => $student->tenant_id, 'course_id' => $course->id,
            'role_title' => 'Data Analyst', 'competencies' => ['SQL'], 'opening_question' => 'Tell me about yourself.', 'is_active' => true,
        ]);

        return MockInterview::query()->create([
            'tenant_id' => $student->tenant_id, 'user_id' => $student->id, 'mock_blueprint_id' => $blueprint->id,
            'mode' => MockInterview::MODE_TEXT, 'status' => MockInterview::STATUS_COMPLETED, 'started_at' => now(), 'completed_at' => now(),
        ]);
    });

    MockCompleted::dispatch($student, $interview);
}

it('opens a module mock requirement at the config default on module completion', function () {
    $module = aModule($this->tenant);

    ModuleCompleted::dispatch($this->student, $module);

    $req = ModuleMockRequirement::withoutGlobalScopes()->where('user_id', $this->student->id)->first();
    expect($req)->not->toBeNull()
        ->and($req->required)->toBe((int) config('mocks.per_module', 3))
        ->and($req->completed)->toBe(0)
        ->and($req->cleared_at)->toBeNull();

    // The student is told their mocks are unlocked.
    expect(InAppNotification::withoutGlobalScopes()->where('user_id', $this->student->id)->where('title', 'like', 'Mocks unlocked%')->count())->toBe(1);
});

it('honours a per-module required_mocks override', function () {
    $module = aModule($this->tenant, requiredMocks: 2);

    ModuleCompleted::dispatch($this->student, $module);

    expect(ModuleMockRequirement::withoutGlobalScopes()->where('user_id', $this->student->id)->value('required'))->toBe(2);
});

it('does not reopen or reset a requirement when the module completes again', function () {
    $module = aModule($this->tenant, requiredMocks: 3);
    ModuleCompleted::dispatch($this->student, $module);
    finishAMock($this->student); // completed = 1

    ModuleCompleted::dispatch($this->student, $module); // re-fire

    $req = ModuleMockRequirement::withoutGlobalScopes()->where('user_id', $this->student->id)->first();
    expect(ModuleMockRequirement::withoutGlobalScopes()->where('user_id', $this->student->id)->count())->toBe(1)
        ->and($req->completed)->toBe(1); // progress preserved
});

it('advances and clears the requirement as mocks are completed', function () {
    $module = aModule($this->tenant, requiredMocks: 3);
    ModuleCompleted::dispatch($this->student, $module);

    finishAMock($this->student);
    finishAMock($this->student);
    expect(ModuleMockRequirement::withoutGlobalScopes()->where('user_id', $this->student->id)->value('completed'))->toBe(2);
    expect(ModuleMockRequirement::withoutGlobalScopes()->where('user_id', $this->student->id)->value('cleared_at'))->toBeNull();

    finishAMock($this->student); // hits the target
    $req = ModuleMockRequirement::withoutGlobalScopes()->where('user_id', $this->student->id)->first();
    expect($req->completed)->toBe(3)->and($req->cleared_at)->not->toBeNull();

    // Cleared notification sent once.
    expect(InAppNotification::withoutGlobalScopes()->where('user_id', $this->student->id)->where('title', 'like', 'Module mocks cleared%')->count())->toBe(1);
});

it('credits mocks to the earliest unlocked module first (FIFO)', function () {
    $first = aModule($this->tenant, requiredMocks: 1);
    $second = aModule($this->tenant, requiredMocks: 2);

    ModuleCompleted::dispatch($this->student, $first);
    ModuleCompleted::dispatch($this->student, $second);

    finishAMock($this->student); // clears the first (needs 1)

    $reqFirst = ModuleMockRequirement::withoutGlobalScopes()->where('module_id', $first->id)->first();
    $reqSecond = ModuleMockRequirement::withoutGlobalScopes()->where('module_id', $second->id)->first();
    expect($reqFirst->cleared_at)->not->toBeNull()
        ->and($reqSecond->completed)->toBe(0);

    finishAMock($this->student); // now flows to the second
    expect(ModuleMockRequirement::withoutGlobalScopes()->where('module_id', $second->id)->value('completed'))->toBe(1);
});

it('is a no-op when a mock is completed with no open requirement', function () {
    finishAMock($this->student);

    expect(ModuleMockRequirement::withoutGlobalScopes()->where('user_id', $this->student->id)->count())->toBe(0);
});

it('does not let another tenant\'s mock advance this student\'s requirement', function () {
    $module = aModule($this->tenant, requiredMocks: 2);
    ModuleCompleted::dispatch($this->student, $module);

    // A student in a different tenant finishing a mock must not touch our requirement.
    $other = Tenant::factory()->create();
    $stranger = User::factory()->for($other)->create(['user_type' => 'student']);
    finishAMock($stranger);

    expect(ModuleMockRequirement::withoutGlobalScopes()->where('user_id', $this->student->id)->value('completed'))->toBe(0);
});

it('exposes module mock progress on the student mock endpoint', function () {
    $module = aModule($this->tenant, requiredMocks: 3);
    ModuleCompleted::dispatch($this->student, $module);
    finishAMock($this->student);

    Sanctum::actingAs($this->student);
    getJson('/api/v1/me/mocks')
        ->assertOk()
        ->assertJsonPath('data.module_mocks.0.module', 'ETL Foundations')
        ->assertJsonPath('data.module_mocks.0.required', 3)
        ->assertJsonPath('data.module_mocks.0.completed', 1)
        ->assertJsonPath('data.module_mocks.0.remaining', 2)
        ->assertJsonPath('data.module_mocks.0.cleared', false);
});
