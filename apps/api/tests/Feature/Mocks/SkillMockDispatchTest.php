<?php

declare(strict_types=1);

use App\Enums\BatchType;
use App\Models\Assignment;
use App\Models\BatchMember;
use App\Models\Course;
use App\Models\InAppNotification;
use App\Models\Lesson;
use App\Models\MockBlueprint;
use App\Models\MockInterview;
use App\Models\Module;
use App\Models\MonetizationSetting;
use App\Models\Tenant;
use App\Models\Topic;
use App\Models\User;
use App\Services\AI\AiClient;
use App\Services\AI\FakeAiClient;
use Database\Seeders\RolePermissionSeeder;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
    $this->tenant = Tenant::factory()->create();
    $this->admin = User::factory()->for($this->tenant)->create(['user_type' => 'staff']);
    $this->admin->assignRole('admin'); // has teach-classes (granted in ADR 0036)
    $this->student = User::factory()->for($this->tenant)->create(['user_type' => 'student']);
    app()->instance(AiClient::class, new FakeAiClient);

    [$this->course, $this->batch, $this->python, $this->sql, $this->assignmentLesson] = withinTenant($this->tenant, function () {
        $course = Course::query()->create(['code' => 'DA', 'name' => 'Data', 'slug' => 'da']);
        $batch = $course->batches()->create(['number' => 'DA-1', 'type' => BatchType::Paid->value]);
        BatchMember::query()->create(['batch_id' => $batch->id, 'user_id' => $this->student->id, 'status' => 'enrolled']);

        $python = MockBlueprint::query()->create([
            'course_id' => $course->id, 'role_title' => 'Data Analyst', 'skill' => 'Python',
            'competencies' => ['Python'], 'opening_question' => 'Reverse a list in Python.', 'is_active' => true,
        ]);
        $sql = MockBlueprint::query()->create([
            'course_id' => $course->id, 'role_title' => 'Data Analyst', 'skill' => 'SQL',
            'competencies' => ['SQL'], 'opening_question' => 'Write a GROUP BY.', 'is_active' => true,
        ]);

        $module = Module::query()->create(['course_id' => $course->id, 'name' => 'M1', 'position' => 1]);
        $topic = Topic::query()->create(['module_id' => $module->id, 'name' => 'T1', 'position' => 1]);
        $lesson = Lesson::query()->create(['topic_id' => $topic->id, 'title' => 'SQL joins exercise', 'type' => 'assignment', 'position' => 1]);
        Assignment::query()->create([
            'tenant_id' => $this->tenant->id, 'lesson_id' => $lesson->id, 'title' => 'SQL joins',
            'instructions' => 'Do it', 'rubric' => [['criterion' => 'Correctness', 'max_points' => 10]], 'max_points' => 10,
        ]);

        return [$course, $batch, $python, $sql, $lesson];
    });
});

it('lets an admin create a skill-tagged blueprint', function () {
    Sanctum::actingAs($this->admin);

    $this->postJson('/api/v1/admin/mock-blueprints', [
        'course_id' => $this->course->id, 'role_title' => 'Data Analyst', 'skill' => 'Statistics',
        'competencies' => ['Statistics'], 'opening_question' => 'Explain p-values.',
    ])->assertCreated()->assertJsonPath('data.skill', 'Statistics');
});

it('offers the student their available skill mocks', function () {
    Sanctum::actingAs($this->student);

    $skills = collect($this->getJson('/api/v1/me/mocks')->assertOk()->json('data.blueprints'))->pluck('skill');
    expect($skills)->toContain('Python')->toContain('SQL');
});

it('starts the specific skill mock the student picked', function () {
    config(['monetization.text_practice_enabled' => true]); // ensure enabled in case
    Sanctum::actingAs($this->student);

    // Force text practice on for the tenant.
    withinTenant($this->tenant, fn () => MonetizationSetting::query()->updateOrCreate(
        ['tenant_id' => $this->tenant->id], ['text_practice_enabled' => true],
    ));

    $this->postJson('/api/v1/me/mocks', ['blueprint_id' => $this->sql->id])->assertCreated();

    $interview = MockInterview::withoutGlobalScopes()->where('user_id', $this->student->id)->firstOrFail();
    expect($interview->mock_blueprint_id)->toBe($this->sql->id);
});

it('dispatches a specific mock to the batch', function () {
    Sanctum::actingAs($this->admin);

    $this->postJson("/api/v1/admin/batches/{$this->batch->id}/dispatch-mock", ['blueprint_id' => $this->python->id])
        ->assertOk()->assertJsonPath('data.notified', 1);

    $note = InAppNotification::withoutGlobalScopes()->where('user_id', $this->student->id)->firstOrFail();
    expect($note->title)->toContain('Python')
        ->and($note->url)->toBe("/mock?start={$this->python->id}");
});

it('dispatches a specific assignment to the batch', function () {
    Sanctum::actingAs($this->admin);

    $this->postJson("/api/v1/admin/batches/{$this->batch->id}/dispatch-assignment", ['lesson_id' => $this->assignmentLesson->id])
        ->assertOk()->assertJsonPath('data.notified', 1);

    $note = InAppNotification::withoutGlobalScopes()->where('user_id', $this->student->id)->firstOrFail();
    expect($note->title)->toContain('SQL joins exercise')
        ->and($note->url)->toBe("/assignments/{$this->assignmentLesson->id}");
});

it('lists dispatch options scoped to the batch course', function () {
    Sanctum::actingAs($this->admin);

    $body = $this->getJson("/api/v1/admin/batches/{$this->batch->id}/dispatch-options")->assertOk();
    expect(collect($body->json('mocks'))->pluck('skill'))->toContain('Python')->toContain('SQL')
        ->and(collect($body->json('assignments'))->pluck('lesson_id'))->toContain($this->assignmentLesson->id);
});

it('refuses to dispatch a mock from another course', function () {
    $otherBlueprint = withinTenant($this->tenant, function () {
        $c = Course::query()->create(['code' => 'XX', 'name' => 'X', 'slug' => 'x']);

        return MockBlueprint::query()->create([
            'course_id' => $c->id, 'role_title' => 'X', 'skill' => 'Go',
            'competencies' => ['Go'], 'opening_question' => 'q', 'is_active' => true,
        ]);
    });

    Sanctum::actingAs($this->admin);
    $this->postJson("/api/v1/admin/batches/{$this->batch->id}/dispatch-mock", ['blueprint_id' => $otherBlueprint->id])
        ->assertNotFound();
});

it('denies dispatch without teach-classes', function () {
    $mentor = User::factory()->for($this->tenant)->create(['user_type' => 'staff']);
    $mentor->assignRole('mentor');
    Sanctum::actingAs($mentor);

    $this->postJson("/api/v1/admin/batches/{$this->batch->id}/dispatch-mock", ['blueprint_id' => $this->python->id])
        ->assertForbidden();
});

it('404s dispatch to another tenant batch', function () {
    $other = Tenant::factory()->create();
    $theirBatch = withinTenant($other, function () use ($other) {
        $c = Course::query()->create(['code' => 'ZZ', 'name' => 'Z', 'slug' => 'z', 'tenant_id' => $other->id]);

        return $c->batches()->create(['number' => 'ZZ-1', 'type' => BatchType::Paid->value, 'tenant_id' => $other->id]);
    });

    Sanctum::actingAs($this->admin);
    $this->postJson("/api/v1/admin/batches/{$theirBatch->id}/dispatch-mock", ['blueprint_id' => $this->python->id])
        ->assertNotFound();
});
