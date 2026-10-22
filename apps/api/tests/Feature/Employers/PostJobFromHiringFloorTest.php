<?php

declare(strict_types=1);

use App\Enums\EmployerJobStatus;
use App\Models\EmployerAgentAction;
use App\Models\EmployerJob;
use App\Models\EmployerMember;
use App\Models\EmployerWorkspace;
use App\Models\Tenant;
use App\Models\User;
use App\Services\AI\AiClient;
use App\Services\AI\FakeAiClient;
use Laravel\Sanctum\Sanctum;

beforeEach(function (): void {
    config(['app.frontend_url' => 'https://browsejobs.ai']);
    $this->tenant = Tenant::factory()->create();
    $this->recruiter = User::factory()->for($this->tenant)->create(['user_type' => 'employer', 'name' => 'Meera Iyer']);
    $this->workspace = EmployerWorkspace::factory()->for($this->tenant)->create(['name' => 'Incture']);
    EmployerMember::factory()->for($this->tenant)->create(['employer_workspace_id' => $this->workspace->id, 'user_id' => $this->recruiter->id]);
    $this->ai = new FakeAiClient;
    app()->instance(AiClient::class, $this->ai);
    $this->url = "/api/v1/employer/workspaces/{$this->workspace->id}/agent";
});

function floorIntent(string $intent): string
{
    return (string) json_encode([
        'intent' => $intent, 'action' => null, 'job' => null, 'round' => null, 'names' => [], 'top_n' => null,
        'from_stage' => null, 'to_stage' => null, 'reason' => null, 'reply' => '',
    ]);
}

it('drafts a role from the Ask bar, takes changes, and posts it only on Go ahead', function (): void {
    Sanctum::actingAs($this->recruiter);
    $this->ai->replies = [
        floorIntent('post_job'),
        json_encode(['title' => 'Data Engineer', 'experience_min_years' => 3, 'experience_max_years' => 5, 'locations' => ['Bengaluru'], 'openings' => 2, 'remote' => false]),
        json_encode(['description' => 'You will build our ingestion pipelines on AWS.', 'skills' => ['Python', 'SQL', 'Airflow']]),
    ];

    $drafted = $this->postJson("{$this->url}/command", ['text' => 'We need 2 data engineers in Bengaluru, 3-5 years, python sql airflow'])->assertOk()->json('data');

    expect($drafted['intent'])->toBe('proposed')
        ->and($drafted['reply'])->toContain('Job draft: Data Engineer')->toContain('3 to 5 years')->toContain('Bengaluru')->toContain('Shall I post it live?')
        ->and($drafted['action']['kind'])->toBe('post_job')
        ->and($drafted['action']['job_id'])->toBeNull()
        ->and(EmployerJob::query()->count())->toBe(0); // nothing is live before the go-ahead

    // A change typed into the same bar edits the draft instead of starting another.
    $this->ai->replies = [
        floorIntent('other'),
        json_encode(['title' => '', 'experience_min_years' => 4, 'experience_max_years' => 7, 'locations' => [], 'openings' => null, 'remote' => false]),
        json_encode(['description' => 'You will build our ingestion pipelines on AWS with Kafka.', 'skills' => ['Python', 'SQL', 'Airflow', 'Kafka'], 'summary' => 'Added Kafka.']),
    ];
    $revised = $this->postJson("{$this->url}/command", ['text' => 'make it 4-7 years and add kafka'])->assertOk()->json('data');

    expect($revised['reply'])->toStartWith('Updated.')->toContain('4 to 7 years')->toContain('Kafka')
        ->and($revised['action']['id'])->toBe($drafted['action']['id'])
        ->and(EmployerAgentAction::query()->where('kind', 'post_job')->count())->toBe(1);

    $done = $this->postJson("{$this->url}/actions/{$drafted['action']['id']}/confirm")->assertOk()->json('data');

    $job = EmployerJob::query()->firstOrFail();
    expect($done['intent'])->toBe('done')
        ->and($done['reply'])->toContain('Data Engineer is live')->toContain("/employer/jobs/{$job->id}?tab=shortlist")
        ->and($job->status)->toBe(EmployerJobStatus::Published)
        ->and($job->experience_min_years)->toBe(4)
        ->and($job->openings)->toBe(2)
        ->and($job->employer_workspace_id)->toBe($this->workspace->id)
        ->and($job->created_by_id)->toBe($this->recruiter->id)
        ->and(EmployerAgentAction::query()->firstOrFail()->employer_job_id)->toBe($job->id);

    // A second tap does not post a second role.
    $this->postJson("{$this->url}/actions/{$drafted['action']['id']}/confirm")->assertOk();
    expect(EmployerJob::query()->count())->toBe(1);
});

it('posts on a spoken yes and drops the draft on not now', function (): void {
    Sanctum::actingAs($this->recruiter);
    $this->ai->replies = [
        floorIntent('post_job'),
        json_encode(['title' => 'Java Developer', 'experience_min_years' => 2, 'experience_max_years' => null, 'locations' => ['Pune'], 'openings' => 1, 'remote' => false]),
        json_encode(['description' => 'Build services in Java.', 'skills' => ['Java', 'Spring']]),
    ];
    $this->postJson("{$this->url}/command", ['text' => 'post a java developer job in pune'])->assertOk();

    $this->postJson("{$this->url}/command", ['text' => 'yes'])->assertOk()->assertJsonPath('data.intent', 'done');
    expect(EmployerJob::query()->sole()->title)->toBe('Java Developer');

    $this->ai->replies = [
        floorIntent('post_job'),
        json_encode(['title' => 'Tester', 'experience_min_years' => 1, 'experience_max_years' => 3, 'locations' => ['Pune'], 'openings' => 1, 'remote' => false]),
        json_encode(['description' => 'Test things.', 'skills' => ['Selenium']]),
    ];
    $second = $this->postJson("{$this->url}/command", ['text' => 'also need a tester in pune'])->assertOk()->json('data');
    $this->postJson("{$this->url}/actions/{$second['action']['id']}/cancel")->assertOk()->assertJsonPath('data.intent', 'held');

    expect(EmployerJob::query()->count())->toBe(1)
        ->and(EmployerAgentAction::query()->find($second['action']['id'])->status)->toBe(EmployerAgentAction::CANCELLED);
});

it('does not let a hiring manager post a role', function (): void {
    $manager = User::factory()->for($this->tenant)->create(['user_type' => 'employer']);
    EmployerMember::factory()->for($this->tenant)->create(['employer_workspace_id' => $this->workspace->id, 'user_id' => $manager->id, 'role' => 'hiring_manager']);
    Sanctum::actingAs($manager);
    $this->ai->replies = [floorIntent('post_job')];

    $reply = $this->postJson("{$this->url}/command", ['text' => 'need a data engineer'])->assertOk()->json('data');

    expect($reply['reply'])->toContain('Only a workspace owner or recruiter')
        ->and(EmployerAgentAction::query()->count())->toBe(0)
        ->and(EmployerJob::query()->count())->toBe(0);
});
