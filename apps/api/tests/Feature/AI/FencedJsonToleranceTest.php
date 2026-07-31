<?php

declare(strict_types=1);

use App\Actions\Employers\DraftJobDescription;
use App\Enums\EmployerApplicationStage;
use App\Jobs\GenerateJdMock;
use App\Jobs\GradeEmployerInterview;
use App\Models\EmployerInterview;
use App\Models\EmployerJob;
use App\Models\EmployerJobApplication;
use App\Models\EmployerWorkspace;
use App\Models\JdMock;
use App\Models\Tenant;
use App\Models\User;
use App\Services\AI\AiClient;
use App\Services\AI\AiGateway;
use App\Services\AI\FakeAiClient;

/**
 * Structured AI features must survive a model that wraps its JSON.
 *
 * Every prompt says "return JSON only", and Claude generally obliges. Other
 * families — DeepSeek and Kimi among them — routinely answer with a ```json
 * fence or a line of preamble, and sixteen features were parsing the reply
 * with a bare json_decode. Switching provider would have broken CV generation,
 * JD drafting, mock generation, grading and the rest, all at once and quietly:
 * each one degrades rather than erroring, so the symptom is "the AI stopped
 * doing anything" with nothing in the logs to explain it.
 *
 * These cover one feature per parsing shape rather than all sixteen — the
 * point is that the tolerant parser is wired in, not that JsonOutput works
 * (it has its own tests).
 */
beforeEach(function (): void {
    $this->fake = new FakeAiClient;
    app()->instance(AiClient::class, $this->fake);

    $this->tenant = Tenant::factory()->create();
    $this->recruiter = User::factory()->for($this->tenant)->create(['user_type' => 'employer']);
    $this->workspace = EmployerWorkspace::factory()->for($this->tenant)->create();
    $this->job = EmployerJob::factory()->for($this->tenant)->published()->create([
        'employer_workspace_id' => $this->workspace->id,
        'created_by_id' => $this->recruiter->id,
    ]);
});

/** A reply in the shape a fencing model actually returns. */
function fenced(array $payload): string
{
    return "Here is the JSON you asked for:\n\n```json\n"
        .json_encode($payload, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT)
        ."\n```\n";
}

it('drafts a JD from a reply wrapped in a markdown fence', function (): void {
    $this->fake->reply = fenced([
        'description' => 'You will own the ingestion pipeline end to end.',
        'skills' => ['sql', 'python'],
        'role_family' => 'data',
        'responsibilities' => ['Own the pipeline'],
        'must_haves' => ['3 years of SQL'],
    ]);

    $draft = app(DraftJobDescription::class)->handle($this->recruiter, $this->workspace, 'Data Engineer');

    expect($draft['skills'])->toBe(['sql', 'python'])
        ->and($draft['source'])->toBe('ai');
});

it('generates a JD mock from a fenced reply', function (): void {
    $this->fake->reply = fenced([
        'questions' => array_map(fn (int $i): array => [
            'id' => $i, 'text' => "Question {$i}", 'skill' => 'sql', 'type' => 'technical', 'weight' => 1,
        ], range(1, 6)),
        'rubric' => ['dimensions' => [
            ['key' => 'technical_depth', 'label' => 'Technical depth', 'weight' => 50, 'criteria' => 'Detail.'],
            ['key' => 'problem_solving', 'label' => 'Problem solving', 'weight' => 30, 'criteria' => 'Structure.'],
            ['key' => 'communication', 'label' => 'Communication', 'weight' => 20, 'criteria' => 'Clarity.'],
        ]],
    ]);

    $pending = JdMock::factory()->for($this->tenant)->create([
        'employer_job_id' => $this->job->id,
        'status' => 'pending',
        'questions' => null,
        'rubric' => null,
    ]);

    (new GenerateJdMock($pending->id))->handle(app(AiGateway::class));

    $mock = $pending->fresh();

    // `source` is the tell: a fallback mock would mean the AI reply was
    // discarded, which is exactly the silent degradation this guards.
    expect($mock->questions)->toHaveCount(6)
        ->and($mock->source)->toBe('ai');
});

it('grades an interview from a fenced reply instead of stalling it', function (): void {
    $this->fake->reply = fenced([
        'dimension_scores' => ['technical_depth' => 84, 'communication' => 70],
        'overall_score' => 78,
        'summary' => 'Strong pipeline detail; communication concise.',
    ]);

    $mock = JdMock::factory()->for($this->tenant)->ready()->create(['employer_job_id' => $this->job->id]);
    $candidate = User::factory()->for($this->tenant)->create(['user_type' => 'student']);
    $application = EmployerJobApplication::factory()->for($this->tenant)->graded()->create([
        'employer_job_id' => $this->job->id,
        'candidate_id' => $candidate->id,
        'jd_mock_id' => $mock->id,
        'stage' => EmployerApplicationStage::L1->value,
    ]);

    $interview = EmployerInterview::factory()->for($this->tenant)->create([
        'employer_job_application_id' => $application->id,
        'status' => 'submitted',
        'answers' => [['question_id' => 1, 'answer' => 'I rebuilt it as an incremental model.']],
    ]);

    (new GradeEmployerInterview($interview->id))->handle(app(AiGateway::class));

    $interview->refresh();

    expect($interview->overall_score)->toBe(78)
        ->and($interview->status->value)->toBe('graded');
});
