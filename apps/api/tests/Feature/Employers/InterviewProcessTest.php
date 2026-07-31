<?php

declare(strict_types=1);

use App\Actions\Employers\SendInterviewRound;
use App\Enums\EmployerApplicationStage;
use App\Enums\EmployerRole;
use App\Events\ApplicationGraded;
use App\Models\EmployerInterview;
use App\Models\EmployerJob;
use App\Models\EmployerJobApplication;
use App\Models\EmployerJobRound;
use App\Models\EmployerMember;
use App\Models\EmployerWorkspace;
use App\Models\JdMock;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Employers\InterviewProcess;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\Sanctum;

/**
 * PRD-E F18 — the interview process an employer designs per JD, and the two
 * ways a round reaches a candidate: by hand and on a score threshold.
 */
beforeEach(function (): void {
    $this->tenant = Tenant::factory()->create();
    $this->recruiter = User::factory()->for($this->tenant)->create(['user_type' => 'employer']);
    $this->workspace = EmployerWorkspace::factory()->for($this->tenant)->create();
    EmployerMember::factory()->for($this->tenant)->create([
        'employer_workspace_id' => $this->workspace->id,
        'user_id' => $this->recruiter->id,
        'role' => EmployerRole::Owner->value,
    ]);
    $this->job = EmployerJob::factory()->for($this->tenant)->published()->create([
        'employer_workspace_id' => $this->workspace->id,
        'created_by_id' => $this->recruiter->id,
    ]);

    // A bank big enough to split between rounds, which is the case the
    // question selection exists for.
    $this->mock = JdMock::factory()->for($this->tenant)->ready()->create([
        'employer_job_id' => $this->job->id,
        'questions' => collect(range(1, 10))->map(fn (int $i): array => [
            'id' => $i,
            'text' => "Question {$i}",
            'skill' => $i <= 5 ? 'sql' : 'python',
            'type' => $i % 2 === 0 ? 'technical' : 'scenario',
            'weight' => 1,
        ])->all(),
    ]);

    $this->candidate = User::factory()->for($this->tenant)->create(['user_type' => 'student']);
    $this->application = EmployerJobApplication::factory()->for($this->tenant)->graded()->create([
        'employer_job_id' => $this->job->id,
        'candidate_id' => $this->candidate->id,
        'jd_mock_id' => $this->mock->id,
        'stage' => EmployerApplicationStage::Shortlisted->value,
        'mock_score' => 82,
    ]);
});

it('provisions the two default rounds on first read so nothing changes for an existing JD', function (): void {
    Sanctum::actingAs($this->recruiter);

    $this->getJson("/api/v1/employer/workspaces/{$this->workspace->id}/jobs/{$this->job->id}/rounds")
        ->assertOk()
        ->assertJsonPath('data.0.key', 'l1')
        ->assertJsonPath('data.1.key', 'l2')
        ->assertJsonPath('data.0.dispatch', 'manual');

    // Reading twice must not create a second pair.
    $this->getJson("/api/v1/employer/workspaces/{$this->workspace->id}/jobs/{$this->job->id}/rounds")->assertOk();

    expect(EmployerJobRound::withoutGlobalScopes()->where('employer_job_id', $this->job->id)->count())->toBe(2);
});

it('saves a designed process and assigns each new round a stable key', function (): void {
    Sanctum::actingAs($this->recruiter);

    $response = $this->putJson("/api/v1/employer/workspaces/{$this->workspace->id}/jobs/{$this->job->id}/rounds", [
        'rounds' => [
            ['key' => 'l1', 'name' => 'Technical screen', 'kind' => 'ai_interview', 'dispatch' => 'manual',
                'focus_skills' => ['sql'], 'question_count' => 4],
            ['name' => 'Hiring manager call', 'kind' => 'human', 'dispatch' => 'manual'],
        ],
    ])->assertOk();

    // The renamed round keeps its key, so interviews already sat under it
    // stay attached instead of forking into a new round.
    expect($response->json('data.0.key'))->toBe('l1')
        ->and($response->json('data.0.name'))->toBe('Technical screen')
        ->and($response->json('data.1.key'))->toBe('hiring_manager_call')
        ->and($response->json('data.1.kind'))->toBe('human');

    // l2 was not in the payload and had no interviews, so it is gone.
    expect(EmployerJobRound::withoutGlobalScopes()->where('employer_job_id', $this->job->id)->count())->toBe(2);
});

it('refuses an automatic round with no threshold rather than interviewing everyone', function (): void {
    Sanctum::actingAs($this->recruiter);

    $this->putJson("/api/v1/employer/workspaces/{$this->workspace->id}/jobs/{$this->job->id}/rounds", [
        'rounds' => [
            ['name' => 'Auto round', 'kind' => 'ai_interview', 'dispatch' => 'auto', 'auto_min_score' => null],
        ],
    ])->assertStatus(422)->assertJsonValidationErrors('rounds.0.auto_min_score');
});

it('disables rather than deletes a round that has already been sat', function (): void {
    $process = app(InterviewProcess::class);
    $l1 = $process->round($this->job, 'l1');
    app(SendInterviewRound::class)->handle($this->application, $l1, $this->recruiter);

    Sanctum::actingAs($this->recruiter);

    $this->putJson("/api/v1/employer/workspaces/{$this->workspace->id}/jobs/{$this->job->id}/rounds", [
        'rounds' => [
            ['key' => 'l2', 'name' => 'L2 — depth', 'kind' => 'ai_interview', 'dispatch' => 'manual'],
        ],
    ])->assertOk();

    $l1 = $l1->fresh();

    expect($l1)->not->toBeNull()
        ->and($l1->enabled)->toBeFalse();

    // The interview it sent still points at it.
    expect(EmployerInterview::withoutGlobalScopes()
        ->where('employer_job_round_id', $l1->id)->count())->toBe(1);
});

it('sends a round by hand and does not send it twice', function (): void {
    $round = app(InterviewProcess::class)->round($this->job, 'l1');

    Sanctum::actingAs($this->recruiter);
    $url = "/api/v1/employer/workspaces/{$this->workspace->id}/jobs/{$this->job->id}"
        ."/applications/{$this->application->id}/rounds/{$round->id}/send";

    $this->postJson($url)->assertCreated()->assertJsonPath('data.round', 'l1');
    $this->postJson($url)->assertCreated();

    expect(EmployerInterview::withoutGlobalScopes()
        ->where('employer_job_application_id', $this->application->id)->count())->toBe(1);
});

it('gives the second round questions the candidate has not already been asked', function (): void {
    $process = app(InterviewProcess::class);
    $send = app(SendInterviewRound::class);

    $first = $send->handle($this->application, $process->round($this->job, 'l1'), $this->recruiter);
    $second = $send->handle($this->application, $process->round($this->job, 'l2'), $this->recruiter);

    $asked = array_column($first->question_set, 'text');
    $again = array_column($second->question_set, 'text');

    // Both rounds used to snapshot the whole JD mock, so a candidate at L2
    // answered the same questions a second time.
    expect(array_intersect($asked, $again))->toBe([])
        ->and(count($asked))->toBe(6)
        ->and(count($again))->toBe(4);
});

it('honours a round focus when picking questions', function (): void {
    $round = app(InterviewProcess::class)->round($this->job, 'l1');
    $round->update(['focus_skills' => ['python'], 'question_count' => 3]);

    $interview = app(SendInterviewRound::class)->handle($this->application, $round, $this->recruiter);

    expect(collect($interview->question_set)->pluck('skill')->unique()->all())->toBe(['python'])
        ->and($interview->question_set)->toHaveCount(3);
});

it('never sends a human round, because the platform does not run it', function (): void {
    $round = app(InterviewProcess::class)->round($this->job, 'l1');
    $round->update(['kind' => EmployerJobRound::KIND_HUMAN]);

    expect(fn () => app(SendInterviewRound::class)->handle($this->application, $round, $this->recruiter))
        ->toThrow(ValidationException::class);
});

it('sends an automatic round when the score clears its bar, and not when it does not', function (): void {
    $round = app(InterviewProcess::class)->round($this->job, 'l1');
    $round->update(['dispatch' => EmployerJobRound::DISPATCH_AUTO, 'auto_min_score' => 75]);

    ApplicationGraded::dispatch($this->application);

    expect(EmployerInterview::withoutGlobalScopes()
        ->where('employer_job_application_id', $this->application->id)->count())->toBe(1);

    // A weaker candidate on the same JD waits for a human instead.
    $weakCandidate = User::factory()->for($this->tenant)->create(['user_type' => 'student']);
    $weak = EmployerJobApplication::factory()->for($this->tenant)->graded()->create([
        'employer_job_id' => $this->job->id,
        'candidate_id' => $weakCandidate->id,
        'jd_mock_id' => $this->mock->id,
        'mock_score' => 61,
    ]);

    ApplicationGraded::dispatch($weak);

    expect(EmployerInterview::withoutGlobalScopes()
        ->where('employer_job_application_id', $weak->id)->count())->toBe(0);
});

it('leaves a manual round alone when a score lands', function (): void {
    ApplicationGraded::dispatch($this->application);

    expect(EmployerInterview::withoutGlobalScopes()
        ->where('employer_job_application_id', $this->application->id)->count())->toBe(0);
});

it('denies a hiring manager the right to redesign the process', function (): void {
    $manager = User::factory()->for($this->tenant)->create(['user_type' => 'employer']);
    EmployerMember::factory()->for($this->tenant)->create([
        'employer_workspace_id' => $this->workspace->id,
        'user_id' => $manager->id,
        'role' => EmployerRole::HiringManager->value,
    ]);

    Sanctum::actingAs($manager);

    $this->putJson("/api/v1/employer/workspaces/{$this->workspace->id}/jobs/{$this->job->id}/rounds", [
        'rounds' => [],
    ])->assertForbidden();
});

it('keeps one tenant out of another tenant interview process', function (): void {
    $other = Tenant::factory()->create();
    $intruder = User::factory()->for($other)->create(['user_type' => 'employer']);
    $otherWorkspace = EmployerWorkspace::factory()->for($other)->create();
    EmployerMember::factory()->for($other)->create([
        'employer_workspace_id' => $otherWorkspace->id,
        'user_id' => $intruder->id,
        'role' => EmployerRole::Owner->value,
    ]);

    Sanctum::actingAs($intruder);

    // Their own workspace, someone else's JD.
    $this->getJson("/api/v1/employer/workspaces/{$otherWorkspace->id}/jobs/{$this->job->id}/rounds")
        ->assertNotFound();

    // Someone else's workspace outright. The tenant scope hides it from
    // route binding, so this 404s before the membership check — and 404 is
    // the better answer, since 403 would confirm the workspace exists.
    $this->getJson("/api/v1/employer/workspaces/{$this->workspace->id}/jobs/{$this->job->id}/rounds")
        ->assertNotFound();

    $this->putJson("/api/v1/employer/workspaces/{$this->workspace->id}/jobs/{$this->job->id}/rounds", [
        'rounds' => [],
    ])->assertNotFound();

    expect(EmployerJobRound::withoutGlobalScopes()->where('employer_job_id', $this->job->id)->count())->toBe(0);
});
