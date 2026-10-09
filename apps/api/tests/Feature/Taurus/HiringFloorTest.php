<?php

declare(strict_types=1);

use App\Enums\EmployerApplicationStage;
use App\Models\AiEvent;
use App\Models\ApplicationStageTransition;
use App\Models\EmployerInterview;
use App\Models\EmployerJob;
use App\Models\EmployerJobApplication;
use App\Models\EmployerMember;
use App\Models\EmployerWorkspace;
use App\Models\Tenant;
use App\Models\User;
use App\Services\AI\AiClient;
use App\Services\AI\FakeAiClient;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;

beforeEach(function (): void {
    $this->tenant = Tenant::factory()->create();
    $this->owner = User::factory()->for($this->tenant)->create(['user_type' => 'employer']);
    $this->workspace = EmployerWorkspace::factory()->for($this->tenant)->create();
    EmployerMember::factory()->for($this->tenant)->owner()->create([
        'employer_workspace_id' => $this->workspace->id,
        'user_id' => $this->owner->id,
    ]);
    $this->job = EmployerJob::factory()->for($this->tenant)->published()->create([
        'employer_workspace_id' => $this->workspace->id,
        'created_by_id' => $this->owner->id,
        'title' => 'Data Analyst',
    ]);
});

function floorApplication(Tenant $tenant, EmployerJob $job, string $stage, ?int $score = null, string $candidateName = 'Priya Candidate'): EmployerJobApplication
{
    return EmployerJobApplication::factory()->for($tenant)->create([
        'employer_job_id' => $job->id,
        'candidate_id' => User::factory()->for($tenant)->create(['name' => $candidateName])->id,
        'stage' => $stage,
        'mock_score' => $score,
        'graded_at' => $score !== null ? now() : null,
    ]);
}

it('draws the pipeline as stations, bots and a name-free feed', function (): void {
    $applied = floorApplication($this->tenant, $this->job, 'applied');
    floorApplication($this->tenant, $this->job, 'graded', 80);
    floorApplication($this->tenant, $this->job, 'graded', 60);
    $shortlisted = floorApplication($this->tenant, $this->job, 'shortlisted', 90, 'Arjun Secret');
    $l1 = floorApplication($this->tenant, $this->job, 'l1', 75);
    floorApplication($this->tenant, $this->job, 'human_round', 82);
    floorApplication($this->tenant, $this->job, 'offer', 88);
    floorApplication($this->tenant, $this->job, 'hired', 91);
    floorApplication($this->tenant, $this->job, 'rejected', 30);

    ApplicationStageTransition::query()->create([
        'tenant_id' => $this->tenant->id,
        'employer_job_application_id' => $shortlisted->id,
        'from_stage' => 'graded',
        'to_stage' => 'shortlisted',
        'actor_type' => 'rule',
        'occurred_at' => now()->subHours(2),
    ]);
    ApplicationStageTransition::query()->create([
        'tenant_id' => $this->tenant->id,
        'employer_job_application_id' => $applied->id,
        'from_stage' => null,
        'to_stage' => 'applied',
        'actor_type' => 'user',
        'occurred_at' => now()->subHour(),
    ]);

    EmployerInterview::factory()->for($this->tenant)->create([
        'employer_job_application_id' => $l1->id,
        'round' => 'l1',
        'status' => 'graded',
        'overall_score' => 70,
        'graded_at' => now()->subMinutes(30),
    ]);
    EmployerInterview::factory()->for($this->tenant)->create([
        'employer_job_application_id' => $shortlisted->id,
        'round' => 'l1',
        'status' => 'in_progress',
        'started_at' => now()->subMinutes(5),
    ]);

    Sanctum::actingAs($this->owner);
    $response = $this->getJson("/api/v1/employer/workspaces/{$this->workspace->id}/hiring-floor")->assertOk();
    $data = $response->json('data');

    expect($data['kpis'])->toBe([
        'open_roles' => 1,
        'in_pipeline' => 7,
        'interviews_in_flight' => 1,
        'offers' => 1,
        'hired' => 1,
    ]);

    $stages = collect($data['stages'])->keyBy('key');
    expect(collect($data['stages'])->pluck('key')->all())
        ->toBe(['applied', 'graded', 'shortlisted', 'l1', 'l2', 'human_round', 'offer', 'hired'])
        ->and($stages['graded']['count'])->toBe(2)
        ->and($stages['graded']['avg_score'])->toEqual(70)
        ->and($stages['l1']['avg_score'])->toEqual(70)      // interview overall_score, not mock score
        ->and($stages['l2']['avg_score'])->toBeNull()
        ->and($stages['shortlisted']['moved_today'])->toBeGreaterThanOrEqual(0)
        ->and($stages['l2']['avg_days_in_stage'])->toBeNull();

    $bots = collect($data['bots'])->keyBy('key');
    expect($bots->keys()->all())->toBe(['sourcing', 'screener', 'shortlist', 'interviewer_l1', 'interviewer_l2', 'scheduler', 'offers', 'onboarding'])
        ->and($bots['scheduler']['status'])->toBe('needs')
        ->and($bots['scheduler']['task'])->toContain('1 candidate waiting for a human interview')
        ->and($bots['interviewer_l1']['status'])->toBe('working')
        ->and($bots['interviewer_l2']['status'])->toBe('idle')
        ->and($bots['shortlist']['status'])->toBe('working')
        ->and($bots['sourcing']['status'])->toBe('working');

    expect(collect($bots['interviewer_l1']['metrics'])->pluck('value', 'label')->all())
        ->toEqual(['In progress' => 1, 'Graded' => 1, 'Avg score' => 70]);

    $events = collect($data['events']);
    expect($events->pluck('message')->all())->toContain('A candidate was shortlisted for Data Analyst')
        ->and($events->pluck('message')->all())->toContain('L1 interview graded 70/100 for Data Analyst')
        ->and($events->firstWhere('stage', 'shortlisted')['actor'])->toBe('Automation')
        ->and($events->firstWhere('stage', 'applied')['actor'])->toBe('Candidate');

    // Newest first.
    expect($events->first()['message'])->toBe('L1 interview graded 70/100 for Data Analyst');

    // Never a candidate's name anywhere on the floor.
    expect($response->getContent())->not->toContain('Arjun Secret')
        ->and($response->getContent())->not->toContain('Priya Candidate');
});

it('filters to one role of this workspace', function (): void {
    $other = EmployerJob::factory()->for($this->tenant)->published()->create([
        'employer_workspace_id' => $this->workspace->id,
        'created_by_id' => $this->owner->id,
    ]);
    floorApplication($this->tenant, $this->job, 'offer', 80);
    floorApplication($this->tenant, $other, 'applied');

    Sanctum::actingAs($this->owner);
    $this->getJson("/api/v1/employer/workspaces/{$this->workspace->id}/hiring-floor?job_id={$this->job->id}")
        ->assertOk()
        ->assertJsonPath('data.kpis.in_pipeline', 1)
        ->assertJsonPath('data.kpis.offers', 1)
        ->assertJsonPath('data.kpis.open_roles', 1);
});

it('404s a job filter from another workspace', function (): void {
    $otherWorkspace = EmployerWorkspace::factory()->for($this->tenant)->create();
    $foreignJob = EmployerJob::factory()->for($this->tenant)->published()->create([
        'employer_workspace_id' => $otherWorkspace->id,
        'created_by_id' => $this->owner->id,
    ]);

    Sanctum::actingAs($this->owner);
    $this->getJson("/api/v1/employer/workspaces/{$this->workspace->id}/hiring-floor?job_id={$foreignJob->id}")
        ->assertNotFound();
    $this->getJson("/api/v1/employer/workspaces/{$this->workspace->id}/hiring-floor?job_id=999999")
        ->assertNotFound();
});

it('refuses a non-member of the workspace', function (): void {
    $outsider = User::factory()->for($this->tenant)->create(['user_type' => 'employer']);
    Sanctum::actingAs($outsider);

    $this->getJson("/api/v1/employer/workspaces/{$this->workspace->id}/hiring-floor")->assertForbidden();
});

it('404s another tenant\'s workspace', function (): void {
    $otherTenant = Tenant::factory()->create();
    $stranger = User::factory()->for($otherTenant)->create(['user_type' => 'employer']);
    Sanctum::actingAs($stranger);

    $this->getJson("/api/v1/employer/workspaces/{$this->workspace->id}/hiring-floor")->assertNotFound();
});

it('is quiet but complete for an empty workspace', function (): void {
    $this->job->delete();

    Sanctum::actingAs($this->owner);
    $data = $this->getJson("/api/v1/employer/workspaces/{$this->workspace->id}/hiring-floor")->assertOk()->json('data');

    expect($data['kpis']['in_pipeline'])->toBe(0)
        ->and($data['stages'])->toHaveCount(8)
        ->and(collect($data['bots'])->pluck('status')->unique()->all())->toBe(['idle'])
        ->and($data['events'])->toBe([]);
});

it('uses EmployerApplicationStage order for stations', function (): void {
    expect(array_map(fn ($s) => $s->value, EmployerApplicationStage::order()))
        ->toBe(['applied', 'graded', 'shortlisted', 'l1', 'l2', 'human_round', 'offer', 'hired']);
});

/** Clean AI slate: no provider keys, the brain on "platform". */
function noBrainKeys(): void
{
    foreach (['anthropic', 'openai', 'gemini', 'kimi', 'deepseek', 'grok', 'groq', 'custom'] as $p) {
        config(["ai.providers.{$p}.api_key" => '']);
    }
    config(['ai.provider' => 'auto', 'ai.providers.custom.base_url' => '', 'taurus.brain.provider' => 'platform', 'taurus.brain.model' => null]);
}

it('lets a member ask Taurus about the floor without exposing a candidate', function (): void {
    noBrainKeys();
    config(['ai.providers.openai.api_key' => 'sk-employer-floor-0009']);
    $fake = new FakeAiClient;
    $fake->reply = 'Two screened candidates are waiting for your review.';
    app()->instance(AiClient::class, $fake);

    floorApplication($this->tenant, $this->job, 'graded', 80, 'Meera Hidden');
    floorApplication($this->tenant, $this->job, 'graded', 60, 'Kabir Hidden');
    floorApplication($this->tenant, $this->job, 'human_round', 82, 'Zoya Hidden');

    Sanctum::actingAs($this->owner);
    $this->postJson("/api/v1/employer/workspaces/{$this->workspace->id}/hiring-floor/ask", ['question' => 'What needs us today?'])
        ->assertOk()
        ->assertJsonPath('data.answer', 'Two screened candidates are waiting for your review.')
        ->assertJsonPath('data.provider', 'openai')
        ->assertJsonStructure(['data' => ['answer', 'provider', 'model']]);

    expect($fake->calls)->toHaveCount(1);
    $prompt = $fake->calls[0]->user;
    expect($prompt)->toContain('What needs us today?')
        ->and($prompt)->toContain('- Screened: 2, avg score 70')
        ->and($prompt)->toContain('- Human round: 1')
        ->and($prompt)->toContain('Interview scheduler (needs)')
        ->and($prompt)->not->toContain('Meera')
        ->and($prompt)->not->toContain('Kabir')
        ->and($prompt)->not->toContain('Zoya');

    $event = AiEvent::withoutGlobalScopes()->sole();
    expect($event->purpose->value)->toBe('taurus')
        ->and($event->user_id)->toBe($this->owner->id);
});

it('routes the employer ask to the Taurus brain provider', function (): void {
    noBrainKeys();
    config(['ai.providers.groq.api_key' => 'gsk-floor-0010', 'taurus.brain.provider' => 'groq']);
    Http::preventStrayRequests();
    Http::fake(['api.groq.com/*' => Http::response([
        'model' => 'llama-3.3-70b-versatile',
        'choices' => [['message' => ['content' => 'Nothing is waiting on you.'], 'finish_reason' => 'stop']],
        'usage' => ['prompt_tokens' => 300, 'completion_tokens' => 9],
    ])]);

    Sanctum::actingAs($this->owner);
    $this->postJson("/api/v1/employer/workspaces/{$this->workspace->id}/hiring-floor/ask", [
        'question' => 'Anything for me?',
        'job_id' => $this->job->id,
    ])->assertOk()
        ->assertJsonPath('data.answer', 'Nothing is waiting on you.')
        ->assertJsonPath('data.provider', 'groq')
        ->assertJsonPath('data.model', 'llama-3.3-70b-versatile');

    Http::assertSent(fn ($request): bool => str_contains((string) $request['messages'][0]['content'], 'Scope: one role — Data Analyst.'));
});

it('answers 503 on the employer ask when Taurus has no brain', function (): void {
    noBrainKeys();
    Sanctum::actingAs($this->owner);

    $this->postJson("/api/v1/employer/workspaces/{$this->workspace->id}/hiring-floor/ask", ['question' => 'Hello?'])
        ->assertStatus(503)
        ->assertJsonPath('error.code', 'brain_not_configured')
        ->assertJsonPath('error.message', "Taurus isn't connected yet. Please try again later.");
});

it('guards the employer ask: members only, own jobs only, a real question', function (): void {
    noBrainKeys();
    config(['ai.providers.openai.api_key' => 'sk-employer-floor-0011']);
    app()->instance(AiClient::class, new FakeAiClient);
    $url = "/api/v1/employer/workspaces/{$this->workspace->id}/hiring-floor/ask";

    $outsider = User::factory()->for($this->tenant)->create(['user_type' => 'employer']);
    Sanctum::actingAs($outsider);
    $this->postJson($url, ['question' => 'How many offers?'])->assertForbidden();

    $stranger = User::factory()->for(Tenant::factory()->create())->create(['user_type' => 'employer']);
    Sanctum::actingAs($stranger);
    $this->postJson($url, ['question' => 'How many offers?'])->assertNotFound();

    $foreignJob = EmployerJob::factory()->for($this->tenant)->published()->create([
        'employer_workspace_id' => EmployerWorkspace::factory()->for($this->tenant)->create()->id,
        'created_by_id' => $this->owner->id,
    ]);
    Sanctum::actingAs($this->owner);
    $this->postJson($url, ['question' => 'How many offers?', 'job_id' => $foreignJob->id])->assertNotFound();
    $this->postJson($url, [])->assertUnprocessable();
    $this->postJson($url, ['question' => str_repeat('a', 501)])->assertUnprocessable();
});
