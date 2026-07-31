<?php

declare(strict_types=1);

use App\Actions\Employers\MoveApplicationStage;
use App\Enums\EmployerApplicationStage;
use App\Enums\EmployerInterviewStatus;
use App\Events\EmployerInterviewGraded;
use App\Jobs\GradeEmployerInterview;
use App\Models\EmployerInterview;
use App\Models\EmployerJob;
use App\Models\EmployerJobApplication;
use App\Models\EmployerMember;
use App\Models\EmployerWorkspace;
use App\Models\JdMock;
use App\Models\Tenant;
use App\Models\User;
use App\Services\AI\AiClient;
use App\Services\AI\AiGateway;
use App\Services\AI\FakeAiClient;
use Illuminate\Support\Facades\Event;
use Laravel\Sanctum\Sanctum;

beforeEach(function (): void {
    $this->fake = new FakeAiClient;
    app()->instance(AiClient::class, $this->fake);

    $this->tenant = Tenant::factory()->create();
    $this->recruiter = User::factory()->for($this->tenant)->create(['user_type' => 'employer']);
    $this->workspace = EmployerWorkspace::factory()->for($this->tenant)->create();
    EmployerMember::factory()->for($this->tenant)->create([
        'employer_workspace_id' => $this->workspace->id,
        'user_id' => $this->recruiter->id,
    ]);
    $this->job = EmployerJob::factory()->for($this->tenant)->published()->create([
        'employer_workspace_id' => $this->workspace->id,
        'created_by_id' => $this->recruiter->id,
    ]);
    $this->mock = JdMock::factory()->for($this->tenant)->ready()->create(['employer_job_id' => $this->job->id]);
    $this->candidate = User::factory()->for($this->tenant)->create(['user_type' => 'student']);
    $this->application = EmployerJobApplication::factory()->for($this->tenant)->graded()->create([
        'employer_job_id' => $this->job->id,
        'candidate_id' => $this->candidate->id,
        'jd_mock_id' => $this->mock->id,
        'stage' => EmployerApplicationStage::Shortlisted->value,
    ]);
});

it('creates an L1 invite with a question snapshot when the application enters L1', function (): void {
    app(MoveApplicationStage::class)->handle(
        $this->application,
        EmployerApplicationStage::L1,
        $this->recruiter,
    );

    $interview = EmployerInterview::withoutGlobalScopes()
        ->where('employer_job_application_id', $this->application->id)
        ->firstOrFail();

    expect($interview->round)->toBe('l1')
        ->and($interview->status)->toBe(EmployerInterviewStatus::Invited)
        ->and($interview->question_set)->toBe($this->mock->questions);

    // Re-firing the stage event is a no-op (unique per application+round).
    app(MoveApplicationStage::class)->handle(
        $this->application->fresh(),
        EmployerApplicationStage::L2,
        $this->recruiter,
    );
    expect(EmployerInterview::withoutGlobalScopes()
        ->where('employer_job_application_id', $this->application->id)->count())->toBe(2);
});

it('lets the candidate start and submit, then grades via AI', function (): void {
    Event::fake([EmployerInterviewGraded::class]);
    $this->fake->reply = json_encode([
        'dimension_scores' => ['technical_depth' => 84, 'communication' => 70],
        'overall_score' => 78,
        'summary' => 'Strong pipeline detail; communication concise. Positive signal.',
    ], JSON_THROW_ON_ERROR);

    $interview = EmployerInterview::factory()->for($this->tenant)->create([
        'employer_job_application_id' => $this->application->id,
    ]);

    Sanctum::actingAs($this->candidate);

    $this->getJson('/api/v1/me/employer-interviews')
        ->assertOk()
        ->assertJsonPath('data.0.id', $interview->id);

    $this->postJson("/api/v1/me/employer-interviews/{$interview->id}/start")
        ->assertOk()
        ->assertJsonPath('data.status', 'in_progress');

    $this->postJson("/api/v1/me/employer-interviews/{$interview->id}/submit", [
        'answers' => [
            ['question_id' => 1, 'answer' => 'I built a daily ELT pipeline with Airflow...'],
            ['question_id' => 2, 'answer' => 'Start with EXPLAIN, check index usage...'],
        ],
    ])->assertOk(); // With the sync test queue, grading runs inline at dispatch.

    $interview = $interview->fresh();
    expect($interview->status)->toBe(EmployerInterviewStatus::Graded)
        ->and($interview->overall_score)->toBe(78)
        ->and($interview->dimension_scores['technical_depth'])->toBe(84);

    Event::assertDispatched(EmployerInterviewGraded::class);

    // Double submit refused.
    $this->postJson("/api/v1/me/employer-interviews/{$interview->id}/submit", [
        'answers' => [['question_id' => 1, 'answer' => 'again']],
    ])->assertUnprocessable();
});

it('never fabricates scores: malformed AI output leaves the round submitted', function (): void {
    $this->fake->reply = 'not json';

    $interview = EmployerInterview::factory()->for($this->tenant)->create([
        'employer_job_application_id' => $this->application->id,
        'status' => EmployerInterviewStatus::Submitted->value,
        'answers' => [['question_id' => 1, 'answer' => 'x']],
        'submitted_at' => now(),
    ]);

    expect(fn () => (new GradeEmployerInterview($interview->id))->handle(app(AiGateway::class)))
        ->toThrow(RuntimeException::class);

    expect($interview->fresh()->status)->toBe(EmployerInterviewStatus::Submitted)
        ->and($interview->fresh()->overall_score)->toBeNull();
});

it('hides grading detail from candidates and shows it to the employer', function (): void {
    $interview = EmployerInterview::factory()->for($this->tenant)->create([
        'employer_job_application_id' => $this->application->id,
        'status' => EmployerInterviewStatus::Graded->value,
        'answers' => [['question_id' => 1, 'answer' => 'x']],
        'dimension_scores' => ['technical_depth' => 80, 'communication' => 60],
        'overall_score' => 72,
        'grading_summary' => 'Solid.',
        'graded_at' => now(),
        'submitted_at' => now(),
    ]);

    Sanctum::actingAs($this->candidate);
    $candidateView = $this->getJson("/api/v1/me/employer-interviews/{$interview->id}")->assertOk()->json('data');
    expect($candidateView)->not->toHaveKey('overall_score')
        ->and($candidateView)->not->toHaveKey('grading_summary');

    Sanctum::actingAs($this->recruiter);
    $this->getJson("/api/v1/employer/workspaces/{$this->workspace->id}/jobs/{$this->job->id}/applications/{$this->application->id}/interviews")
        ->assertOk()
        ->assertJsonPath('data.0.overall_score', 72)
        ->assertJsonPath('data.0.grading_summary', 'Solid.');

    // Another candidate cannot read someone else's interview.
    $other = User::factory()->for($this->tenant)->create(['user_type' => 'student']);
    Sanctum::actingAs($other);
    $this->getJson("/api/v1/me/employer-interviews/{$interview->id}")->assertNotFound();
});

it('expires an overdue round instead of accepting answers', function (): void {
    $interview = EmployerInterview::factory()->for($this->tenant)->create([
        'employer_job_application_id' => $this->application->id,
        'expires_at' => now()->subHour(),
    ]);

    Sanctum::actingAs($this->candidate);

    $this->postJson("/api/v1/me/employer-interviews/{$interview->id}/start")->assertUnprocessable();
    expect($interview->fresh()->status)->toBe(EmployerInterviewStatus::Expired);
});
