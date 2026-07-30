<?php

declare(strict_types=1);

use App\Enums\JdMockStatus;
use App\Events\JdMockGenerated;
use App\Jobs\GenerateJdMock;
use App\Models\EmployerJob;
use App\Models\EmployerMember;
use App\Models\EmployerWorkspace;
use App\Models\JdMock;
use App\Models\Tenant;
use App\Models\User;
use App\Services\AI\AiClient;
use App\Services\AI\AiGateway;
use App\Services\AI\FakeAiClient;
use Illuminate\Support\Facades\Bus;
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
    $this->job = EmployerJob::factory()->for($this->tenant)->create([
        'employer_workspace_id' => $this->workspace->id,
        'created_by_id' => $this->recruiter->id,
    ]);
});

function validMockJson(): string
{
    $questions = [];
    for ($i = 1; $i <= 8; $i++) {
        $questions[] = ['id' => $i, 'text' => "Question {$i} about python?", 'skill' => 'python', 'type' => 'technical', 'weight' => 1];
    }

    return json_encode([
        'questions' => $questions,
        'rubric' => ['dimensions' => [
            ['key' => 'technical_depth', 'label' => 'Technical depth', 'weight' => 40, 'criteria' => 'Depth.'],
            ['key' => 'problem_solving', 'label' => 'Problem solving', 'weight' => 40, 'criteria' => 'Structure.'],
            ['key' => 'communication', 'label' => 'Communication', 'weight' => 20, 'criteria' => 'Clarity.'],
        ]],
    ], JSON_THROW_ON_ERROR);
}

it('generates a ready mock from a valid AI response', function (): void {
    Event::fake([JdMockGenerated::class]);
    $this->fake->reply = validMockJson();

    $mock = JdMock::factory()->for($this->tenant)->create(['employer_job_id' => $this->job->id]);

    (new GenerateJdMock($mock->id))->handle(app(AiGateway::class));

    $mock = $mock->fresh();
    expect($mock->status)->toBe(JdMockStatus::Ready)
        ->and($mock->source)->toBe('ai')
        ->and(count($mock->questions))->toBe(8)
        ->and($mock->rubric['dimensions'])->toHaveCount(3);

    Event::assertDispatched(JdMockGenerated::class);
});

it('falls back to a deterministic mock on malformed AI output', function (): void {
    $this->fake->reply = 'Sorry, here is some prose instead of JSON.';

    $mock = JdMock::factory()->for($this->tenant)->create(['employer_job_id' => $this->job->id]);

    (new GenerateJdMock($mock->id))->handle(app(AiGateway::class));

    $mock = $mock->fresh();
    expect($mock->status)->toBe(JdMockStatus::Ready)
        ->and($mock->source)->toBe('fallback')
        ->and(count($mock->questions))->toBeGreaterThanOrEqual(3)
        ->and($mock->rubric['dimensions'])->toHaveCount(4);
});

it('is idempotent: a non-pending mock is never reprocessed', function (): void {
    $ready = JdMock::factory()->for($this->tenant)->ready()->create(['employer_job_id' => $this->job->id]);
    $originalQuestions = $ready->questions;

    $this->fake->reply = validMockJson();
    (new GenerateJdMock($ready->id))->handle(app(AiGateway::class));

    expect($ready->fresh()->questions)->toBe($originalQuestions)
        ->and($this->fake->calls)->toBeEmpty();
});

it('exposes the mock via the API and regenerates as a new version', function (): void {
    // Fake the bus: with the sync queue the pending version would be
    // generated inline and the in-flight guard would never be observable.
    Bus::fake([GenerateJdMock::class]);

    JdMock::factory()->for($this->tenant)->ready()->create(['employer_job_id' => $this->job->id]);

    Sanctum::actingAs($this->recruiter);

    $this->getJson("/api/v1/employer/workspaces/{$this->workspace->id}/jobs/{$this->job->id}/mock")
        ->assertOk()
        ->assertJsonPath('data.version', 1)
        ->assertJsonPath('data.status', 'ready');

    $this->postJson("/api/v1/employer/workspaces/{$this->workspace->id}/jobs/{$this->job->id}/mock/regenerate")
        ->assertAccepted()
        ->assertJsonPath('data.version', 2)
        ->assertJsonPath('data.status', 'pending');

    // A second regenerate while v2 is pending is refused.
    $this->postJson("/api/v1/employer/workspaces/{$this->workspace->id}/jobs/{$this->job->id}/mock/regenerate")
        ->assertUnprocessable();

    Bus::assertDispatched(GenerateJdMock::class, 1);
});
