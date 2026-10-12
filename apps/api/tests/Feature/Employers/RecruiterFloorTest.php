<?php

declare(strict_types=1);

use App\Enums\EmployerInterviewStatus;
use App\Models\EmployerCandidate;
use App\Models\EmployerInterview;
use App\Models\EmployerJob;
use App\Models\EmployerJobApplication;
use App\Models\EmployerMember;
use App\Models\EmployerWhatsappLink;
use App\Models\EmployerWhatsappMessage;
use App\Models\EmployerWorkspace;
use App\Models\JobShortlistEntry;
use App\Models\JobShortlistRun;
use App\Models\Tenant;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

beforeEach(function (): void {
    $this->tenant = Tenant::factory()->create();
    $this->owner = User::factory()->for($this->tenant)->create(['user_type' => 'employer', 'name' => 'Krishna']);
    $this->workspace = EmployerWorkspace::factory()->for($this->tenant)->create(['name' => 'Incture']);
    EmployerMember::factory()->for($this->tenant)->owner()->create([
        'employer_workspace_id' => $this->workspace->id, 'user_id' => $this->owner->id,
    ]);
    $this->url = "/api/v1/employer/workspaces/{$this->workspace->id}/recruiter-floor";
});

it('shows an honest empty floor before any job exists', function (): void {
    Sanctum::actingAs($this->owner);

    $data = $this->getJson($this->url)->assertOk()->json('data');

    expect($data['job'])->toBeNull()
        ->and(collect($data['zones'])->pluck('id')->all())->toBe(['intake', 'shortlist', 'bgv', 'final', 'invites', 'l1', 'l2', 'report'])
        ->and(collect($data['agents'])->firstWhere('id', 'intake')['task'])->toContain('Scan the WhatsApp QR')
        ->and(collect($data['agents'])->firstWhere('id', 'bgv')['live'])->toBeFalse()
        ->and($data['people'])->toBe([]);
});

it('draws the real pipeline: WhatsApp intake, shortlist picks, interviews and scores', function (): void {
    $job = EmployerJob::factory()->for($this->tenant)->published()->create([
        'employer_workspace_id' => $this->workspace->id, 'created_by_id' => $this->owner->id, 'title' => 'Data Engineer',
    ]);
    $link = EmployerWhatsappLink::create([
        'tenant_id' => $this->tenant->id, 'employer_workspace_id' => $this->workspace->id, 'user_id' => $this->owner->id,
        'phone' => '919845011111', 'state' => 'confirming', 'draft' => ['title' => 'QA'],
    ]);
    EmployerWhatsappMessage::create(['tenant_id' => $this->tenant->id, 'employer_whatsapp_link_id' => $link->id, 'phone' => '919845011111', 'direction' => 'in', 'body' => 'Need a QA engineer', 'status' => 'processed']);

    $run = JobShortlistRun::create([
        'tenant_id' => $this->tenant->id, 'employer_job_id' => $job->id, 'trigger' => 'publish',
        'settings' => ['top_n' => 10], 'considered' => ['browsejobs' => ['considered' => 4, 'matched' => 4], 'naukri' => ['considered' => 20, 'matched' => 6]],
        'shortlisted_count' => 2,
    ]);
    $naukri = EmployerCandidate::create([
        'tenant_id' => $this->tenant->id, 'employer_workspace_id' => $this->workspace->id, 'source' => 'naukri',
        'dedupe_key' => 'p:9000000001', 'name' => 'Asha Rao', 'phone' => '9000000001', 'current_title' => 'Data Engineer',
    ]);
    JobShortlistEntry::create([
        'tenant_id' => $this->tenant->id, 'employer_job_id' => $job->id, 'job_shortlist_run_id' => $run->id, 'source' => 'naukri',
        'employer_candidate_id' => $naukri->id, 'candidate_key' => 'c:'.$naukri->id, 'match_score' => 88, 'rank' => 1,
        'reasons' => ['matched_skills' => ['python'], 'missing_skills' => ['aws'], 'experience' => '4 yrs — in range'],
    ]);

    $student = User::factory()->for($this->tenant)->create(['user_type' => 'student', 'name' => 'Ravi Kumar', 'phone' => '9111111111']);
    $app = EmployerJobApplication::factory()->for($this->tenant)->create([
        'employer_job_id' => $job->id, 'candidate_id' => $student->id, 'stage' => 'l1', 'mock_score' => 72,
    ]);
    EmployerInterview::factory()->for($this->tenant)->create([
        'employer_job_application_id' => $app->id, 'round' => 'l1', 'status' => EmployerInterviewStatus::Graded->value,
        'overall_score' => 81, 'graded_at' => now(),
    ]);

    Sanctum::actingAs($this->owner);
    $data = $this->getJson($this->url)->assertOk()->json('data');

    $agents = collect($data['agents'])->keyBy('id');
    $people = collect($data['people'])->keyBy('name');

    expect($data['job']['title'])->toBe('Data Engineer')
        ->and($agents['intake']['state'])->toBe('approval')
        ->and($agents['shortlist']['task'])->toContain('Picked 1 of 24')->toContain('Naukri 20')
        ->and($agents['l1']['task'])->toContain('1 L1 interview scored · average 81')
        ->and($people['Asha Rao']['zone'])->toBe('shortlist')
        ->and($people['Asha Rao']['source'])->toBe('Naukri')
        ->and($people['Ravi Kumar']['zone'])->toBe('l1')
        ->and($people['Ravi Kumar']['scores']['l1'])->toBe(81)
        ->and(json_encode($data))->not->toContain('9111111111') // a student's phone never leaves the server
        ->and(collect($data['activity'])->pluck('text')->implode(' '))->toContain('Need a QA engineer')->toContain('Ravi Kumar scored 81 in L1');
});

it('is only for members of the workspace', function (): void {
    $stranger = User::factory()->for($this->tenant)->create(['user_type' => 'employer']);
    $other = EmployerWorkspace::factory()->for($this->tenant)->create();
    EmployerMember::factory()->for($this->tenant)->create(['employer_workspace_id' => $other->id, 'user_id' => $stranger->id]);
    Sanctum::actingAs($stranger);
    $this->getJson($this->url)->assertForbidden();

    Sanctum::actingAs(User::factory()->for(Tenant::factory()->create())->create(['user_type' => 'employer']));
    $this->getJson($this->url)->assertNotFound();
});
