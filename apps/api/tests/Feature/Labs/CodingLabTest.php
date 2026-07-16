<?php

declare(strict_types=1);

use App\Actions\Labs\RunCode;
use App\Enums\SubmissionKind;
use App\Models\ActivityEvent;
use App\Models\CodeSubmission;
use App\Models\CodingLab;
use App\Models\Lesson;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Judge0\FakeJudge0Client;
use App\Support\Judge0\Judge0Client;
use App\Support\Judge0\Judge0Result;
use Database\Seeders\RolePermissionSeeder;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    // Fake Judge0 that computes the sum of the newline-separated stdin integers.
    $fake = new FakeJudge0Client;
    $fake->handler = fn ($lang, $src, $stdin) => new Judge0Result(
        stdout: (string) array_sum(array_map('intval', array_filter(explode("\n", trim((string) $stdin)), fn ($x) => $x !== ''))),
        stderr: '', compileOutput: '', statusId: 3, statusText: 'Accepted', timeMs: 10, memoryKb: 2000,
    );
    app()->instance(Judge0Client::class, $fake);

    $this->tenant = Tenant::factory()->create();
    $this->student = User::factory()->for($this->tenant)->create(['user_type' => 'student']);
    $this->lab = CodingLab::factory()->for($this->tenant)->create();
});

it('runs code and records a code_run submission + activity', function () {
    $sub = withinTenant($this->tenant, fn () => app(RunCode::class)->handle($this->student, $this->lab, 'print(1+1)', SubmissionKind::Run));

    expect($sub->kind)->toBe(SubmissionKind::Run)
        ->and($sub->total_tests)->toBe(0)
        ->and(ActivityEvent::withoutGlobalScopes()->where('type', 'code_run')->count())->toBe(1);
});

it('grades a submission against the hidden test cases', function () {
    // A correct program: sum two integers.
    $sub = withinTenant($this->tenant, fn () => app(RunCode::class)->handle($this->student, $this->lab, "a=int(input())\nb=int(input())\nprint(a+b)", SubmissionKind::Submit));

    expect($sub->kind)->toBe(SubmissionKind::Submit)
        ->and($sub->passed_tests)->toBe(2)      // factory has 2 test cases
        ->and($sub->total_tests)->toBe(2)
        ->and(ActivityEvent::withoutGlobalScopes()->where('type', 'code_submitted')->value('value'))->toBe(2);
});

it('serves the lab to the student without expected outputs', function () {
    Sanctum::actingAs($this->student);

    $this->getJson("/api/v1/me/labs/{$this->lab->lesson_id}")
        ->assertOk()
        ->assertJsonPath('data.language', 'python')
        ->assertJsonPath('data.test_count', 2)
        ->assertJsonMissingPath('data.test_cases');
});

it('runs code through the API', function () {
    Sanctum::actingAs($this->student);

    $this->postJson("/api/v1/me/labs/{$this->lab->lesson_id}/submit", ['source' => "a=int(input())\nb=int(input())\nprint(a+b)"])
        ->assertCreated()
        ->assertJsonPath('data.passed_tests', 2);

    expect(CodeSubmission::withoutGlobalScopes()->where('user_id', $this->student->id)->count())->toBe(1);
});

it('404s a foreign tenant lab', function () {
    $foreign = CodingLab::factory()->for(Tenant::factory()->create())->create();

    Sanctum::actingAs($this->student);

    $this->getJson("/api/v1/me/labs/{$foreign->lesson_id}")->assertNotFound();
});

it('admin upserts coding-lab content', function () {
    $this->seed(RolePermissionSeeder::class);
    $admin = User::factory()->for($this->tenant)->create(['user_type' => 'staff']);
    $admin->assignRole('admin');
    $lesson = Lesson::factory()->for($this->tenant)->codingLab()->create();

    Sanctum::actingAs($admin);

    $this->putJson("/api/v1/admin/lessons/{$lesson->id}/coding-lab", [
        'language' => 'python',
        'instructions' => 'Print hello.',
        'starter_code' => '# code here',
        'test_cases' => [['stdin' => '', 'expected_stdout' => 'hello']],
    ])->assertOk()->assertJsonPath('data.language', 'python');

    expect(CodingLab::withoutGlobalScopes()->where('lesson_id', $lesson->id)->exists())->toBeTrue();
});
