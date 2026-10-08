<?php

declare(strict_types=1);

use App\Models\Course;
use App\Models\EmployerJob;
use App\Models\EmployerWorkspace;
use App\Models\MockBlueprint;
use App\Models\MockInterview;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Laravel\Sanctum\Sanctum;

use function Pest\Laravel\getJson;

beforeEach(function () {
    $this->tenant = Tenant::factory()->create();
});

/**
 * One student with one completed interview of each kind.
 *
 * @return array{student: User, ids: array<string, int>}
 */
function studentWithEveryKind(Tenant $tenant): array
{
    $student = User::factory()->for($tenant)->create(['user_type' => 'student']);
    $course = Course::factory()->for($tenant)->create();
    $workspace = EmployerWorkspace::factory()->for($tenant)->create();
    $job = EmployerJob::factory()->for($tenant)->create(['employer_workspace_id' => $workspace->id]);

    $blueprints = [
        'course' => MockBlueprint::factory()->for($tenant)->create(['course_id' => $course->id, 'role_title' => 'Data Engineer']),
        'job' => MockBlueprint::factory()->for($tenant)->create(['course_id' => $course->id, 'employer_job_id' => $job->id, 'role_title' => 'Data Engineer · BrowseJobs']),
        'cv' => MockBlueprint::factory()->for($tenant)->create(['course_id' => $course->id, 'user_id' => $student->id, 'role_title' => 'AI Readiness Interview']),
    ];

    $make = fn (MockBlueprint $b, array $extra = []) => MockInterview::withoutGlobalScopes()->create([
        'tenant_id' => $tenant->id,
        'user_id' => $student->id,
        'mock_blueprint_id' => $b->id,
        'mode' => 'text',
        'is_room' => false,
        'status' => MockInterview::STATUS_COMPLETED,
        'overall_score' => 60,
        'started_at' => now()->subHour(),
        'completed_at' => now(),
        ...$extra,
    ])->id;

    return ['student' => $student, 'ids' => [
        'practice' => $make($blueprints['course']),
        'voice' => $make($blueprints['course'], ['is_room' => true]),
        'job' => $make($blueprints['job'], ['is_room' => true]),
        'cv' => $make($blueprints['cv'], ['is_room' => true]),
    ]];
}

it('derives one kind per interview and lists each kind on its own', function () {
    ['student' => $student, 'ids' => $ids] = studentWithEveryKind($this->tenant);
    Sanctum::actingAs($student);

    foreach ($ids as $kind => $id) {
        getJson('/api/v1/me/mocks/history?kind='.$kind)
            ->assertOk()
            ->assertJsonCount(1, 'data.mocks')
            ->assertJsonPath('data.mocks.0.id', $id)
            ->assertJsonPath('data.mocks.0.kind', $kind);

        getJson("/api/v1/me/mocks/{$id}")->assertOk()->assertJsonPath('data.kind', $kind);
    }

    getJson('/api/v1/me/mocks')
        ->assertOk()
        ->assertJsonPath('data.kind_counts.practice', 1)
        ->assertJsonPath('data.kind_counts.voice', 1)
        ->assertJsonPath('data.kind_counts.job', 1)
        ->assertJsonPath('data.kind_counts.cv', 1);
});

it('treats a voice-mode call on a course blueprint as voice', function () {
    ['student' => $student, 'ids' => $ids] = studentWithEveryKind($this->tenant);
    withinTenant($this->tenant, fn () => MockInterview::query()->whereKey($ids['practice'])->update(['mode' => 'voice']));
    Sanctum::actingAs($student);

    getJson('/api/v1/me/mocks/history?kind=voice')->assertOk()->assertJsonCount(2, 'data.mocks');
    getJson('/api/v1/me/mocks/history?kind=practice')->assertOk()->assertJsonCount(0, 'data.mocks');
});

it('only offers to resume an unfinished practice interview on the practice card', function () {
    ['student' => $student, 'ids' => $ids] = studentWithEveryKind($this->tenant);
    withinTenant($this->tenant, fn () => MockInterview::query()->whereKey($ids['job'])
        ->update(['status' => MockInterview::STATUS_IN_PROGRESS, 'completed_at' => null]));
    Sanctum::actingAs($student);

    getJson('/api/v1/me/mocks')->assertOk()->assertJsonPath('data.in_progress_id', null);

    withinTenant($this->tenant, fn () => MockInterview::query()->whereKey($ids['practice'])
        ->update(['status' => MockInterview::STATUS_IN_PROGRESS, 'completed_at' => null]));

    getJson('/api/v1/me/mocks')->assertOk()->assertJsonPath('data.in_progress_id', $ids['practice']);
});

it('rejects an unknown kind', function () {
    $student = User::factory()->for($this->tenant)->create(['user_type' => 'student']);
    Sanctum::actingAs($student);

    getJson('/api/v1/me/mocks/history?kind=everything')->assertUnprocessable();
});

it('never lists another student\'s interviews', function () {
    studentWithEveryKind($this->tenant);
    $other = User::factory()->for($this->tenant)->create(['user_type' => 'student']);
    Sanctum::actingAs($other);

    foreach (MockInterview::KINDS as $kind) {
        getJson('/api/v1/me/mocks/history?kind='.$kind)->assertOk()->assertJsonCount(0, 'data.mocks');
    }
});

it('gives admins one table per kind with counts', function () {
    $this->seed(RolePermissionSeeder::class);
    ['ids' => $ids] = studentWithEveryKind($this->tenant);
    $admin = User::factory()->for($this->tenant)->create(['user_type' => 'staff']);
    $admin->assignRole('admin');
    Sanctum::actingAs($admin);

    foreach ($ids as $kind => $id) {
        getJson('/api/v1/admin/mock-interviews?kind='.$kind)
            ->assertOk()
            ->assertJsonCount(1, 'data.interviews')
            ->assertJsonPath('data.interviews.0.id', $id)
            ->assertJsonPath('data.counts.'.$kind, 1);
    }

    getJson('/api/v1/admin/mock-interviews?kind=nope')->assertUnprocessable();
});

it('denies the admin interview tables to students', function () {
    $this->seed(RolePermissionSeeder::class);
    $student = User::factory()->for($this->tenant)->create(['user_type' => 'student']);
    Sanctum::actingAs($student);

    getJson('/api/v1/admin/mock-interviews?kind=practice')->assertForbidden();
});

it('never shows one tenant\'s interviews to another tenant\'s admin', function () {
    $this->seed(RolePermissionSeeder::class);
    studentWithEveryKind($this->tenant);
    $otherTenant = Tenant::factory()->create();
    $admin = User::factory()->for($otherTenant)->create(['user_type' => 'staff']);
    $admin->assignRole('admin');
    Sanctum::actingAs($admin);

    foreach (MockInterview::KINDS as $kind) {
        getJson('/api/v1/admin/mock-interviews?kind='.$kind)
            ->assertOk()
            ->assertJsonCount(0, 'data.interviews')
            ->assertJsonPath('data.counts.'.$kind, 0);
    }
});
