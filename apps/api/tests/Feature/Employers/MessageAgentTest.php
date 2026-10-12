<?php

declare(strict_types=1);

use App\Enums\EmployerInterviewStatus;
use App\Events\MockCompleted;
use App\Models\EmployerCandidate;
use App\Models\EmployerInterview;
use App\Models\EmployerJob;
use App\Models\EmployerJobApplication;
use App\Models\EmployerMember;
use App\Models\EmployerWorkspace;
use App\Models\InterviewInvite;
use App\Models\JdMock;
use App\Models\JobShortlistEntry;
use App\Models\MockInterview;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Employers\InterviewTemplates;
use App\Support\WhatsApp\FakeWhatsAppClient;
use App\Support\WhatsApp\WhatsAppClient;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;

beforeEach(function (): void {
    config(['app.frontend_url' => 'https://browsejobs.ai']);
    $this->tenant = Tenant::factory()->create();
    $this->recruiter = User::factory()->for($this->tenant)->create(['user_type' => 'employer']);
    $this->workspace = EmployerWorkspace::factory()->for($this->tenant)->create(['name' => 'Incture']);
    EmployerMember::factory()->for($this->tenant)->create(['employer_workspace_id' => $this->workspace->id, 'user_id' => $this->recruiter->id]);
    $this->job = EmployerJob::factory()->for($this->tenant)->published()->create([
        'employer_workspace_id' => $this->workspace->id, 'created_by_id' => $this->recruiter->id, 'title' => 'Data Engineer',
    ]);
    JdMock::factory()->for($this->tenant)->ready()->create(['employer_job_id' => $this->job->id]);

    $this->naukri = EmployerCandidate::create([
        'tenant_id' => $this->tenant->id, 'employer_workspace_id' => $this->workspace->id, 'source' => 'naukri',
        'dedupe_key' => 'p:9845011111', 'name' => 'Asha Rao', 'phone' => '9845011111', 'skills' => ['python'],
    ]);
    $this->entry = JobShortlistEntry::create([
        'tenant_id' => $this->tenant->id, 'employer_job_id' => $this->job->id, 'source' => 'naukri',
        'employer_candidate_id' => $this->naukri->id, 'candidate_key' => 'c:'.$this->naukri->id,
        'match_score' => 90, 'rank' => 1, 'reasons' => ['matched_skills' => ['python'], 'missing_skills' => []],
    ]);

    $this->wa = new FakeWhatsAppClient;
    app()->instance(WhatsAppClient::class, $this->wa);
    $this->base = "/api/v1/employer/workspaces/{$this->workspace->id}/jobs/{$this->job->id}/shortlist";
});

function approveTemplates(): void
{
    Cache::put('interview_template_statuses', [InterviewTemplates::INVITE => 'APPROVED', InterviewTemplates::LINK => 'APPROVED'], 600);
}

function candidateSays(string $text): string
{
    Artisan::call('employer:whatsapp-inbound', ['from' => '919845011111', '--text' => $text, '--id' => 'wamid.'.uniqid()]);

    return trim(Artisan::output());
}

it('will not message anyone until Meta approves the invite template, and can submit it', function (): void {
    Cache::put('interview_template_statuses', [InterviewTemplates::INVITE => null, InterviewTemplates::LINK => null], 600);
    Sanctum::actingAs($this->recruiter);

    $this->postJson("{$this->base}/invite")->assertUnprocessable()->assertJsonValidationErrors('invite');
    expect($this->wa->sent)->toBe([]);

    config(['services.whatsapp.business_account_id' => 'WABA1', 'services.whatsapp.access_token' => 'test-token']);
    Http::fake([
        'graph.facebook.com/*/message_templates?*' => Http::response(['data' => []]),
        'graph.facebook.com/*/message_templates' => Http::response(['id' => '1', 'status' => 'PENDING']),
    ]);
    $result = $this->postJson("{$this->base}/templates")->assertOk()->json('data.template_result');
    expect($result[InterviewTemplates::INVITE])->toContain('submitted');
    Http::assertSent(fn ($r) => $r->method() === 'POST' && $r['name'] === InterviewTemplates::INVITE && $r['category'] === 'UTILITY');
});

it('invites the shortlist on WhatsApp, giving a Naukri candidate a light account the CRM will skip', function (): void {
    approveTemplates();
    Sanctum::actingAs($this->recruiter);

    $data = $this->postJson("{$this->base}/invite")->assertOk()->json('data');
    expect($data['invite_result']['queued'])->toBe(1);

    $user = User::query()->find($this->naukri->fresh()->user_id);
    expect($user)->not->toBeNull()
        ->and($user->phone)->toBe('+919845011111')
        ->and($user->user_type)->toBe('student')
        ->and(EmployerJobApplication::query()->where('candidate_id', $user->id)->first()->stage->value)->toBe('shortlisted');

    $sent = $this->wa->sent[0];
    expect($sent['template'])->toBe(InterviewTemplates::INVITE)
        ->and($sent['to'])->toBe('919845011111')
        ->and(array_slice($sent['parameters'], 0, 3))->toBe(['Asha', 'Incture', 'Data Engineer'])
        ->and(InterviewInvite::query()->value('status'))->toBe('sent');

    // Pressing it again never messages the same person twice.
    expect($this->postJson("{$this->base}/invite")->json('data.invite_result.skipped'))->toBe(['already invited' => 1])
        ->and($this->wa->sent)->toHaveCount(1);
});

it('books the day the candidate picks, and lets them decline', function (): void {
    approveTemplates();
    Sanctum::actingAs($this->recruiter);
    $this->postJson("{$this->base}/invite")->assertOk();
    $invite = InterviewInvite::query()->firstOrFail();

    expect(candidateSays('hello?'))->toBe('queued')
        ->and(end($this->wa->sent)['body'])->toContain('Please reply with the number');

    candidateSays('2');
    $invite->refresh();
    expect($invite->status)->toBe('scheduled')
        ->and($invite->chosen_date->toDateString())->toBe($invite->date_options[1])
        ->and(end($this->wa->sent)['body'])->toContain('Booked for');

    candidateSays('sorry, not interested');
    expect($invite->fresh()->status)->toBe('scheduled'); // "sorry" first — not a clear no; stays booked

    candidateSays('not interested');
    expect($invite->fresh()->status)->toBe('declined');
});

it('opens the booked interview on the day, sends the link, runs it in the voice room and scores it', function (): void {
    approveTemplates();
    Sanctum::actingAs($this->recruiter);
    $this->postJson("{$this->base}/invite")->assertOk();
    candidateSays('1');
    $invite = InterviewInvite::query()->firstOrFail();

    $this->travelTo($invite->chosen_date->copy()->setTime(10, 0, 0, 0)->shiftTimezone('Asia/Kolkata'));
    Artisan::call('interviews:open-due');

    $invite->refresh();
    $interview = EmployerInterview::query()->findOrFail($invite->employer_interview_id);
    $link = collect($this->wa->sent)->firstWhere('template', InterviewTemplates::LINK);
    expect($invite->status)->toBe('open')
        ->and($interview->round)->toBe('l1')
        ->and(EmployerJobApplication::query()->find($interview->employer_job_application_id)->stage->value)->toBe('l1')
        ->and($link['parameters'])->toBe(['Asha', 'L1', 'Data Engineer']);

    // Their reply ("OK") opens the 24-hour window and gets the link as free text.
    candidateSays('OK');
    expect(end($this->wa->sent)['body'])->toContain("https://browsejobs.ai/hire-interview/{$invite->token}");

    // The public page says what it is, nothing personal.
    $public = $this->getJson("/api/v1/interview-invites/{$invite->token}")->assertOk()->json('data');
    expect($public)->toMatchArray(['first_name' => 'Asha', 'role' => 'Data Engineer', 'company' => 'Incture', 'round' => 'L1', 'open' => true])
        ->and(json_encode($public))->not->toContain('9845011111');

    // Someone else's login cannot use the link.
    Sanctum::actingAs(User::factory()->for($this->tenant)->create(['user_type' => 'student']));
    $this->postJson("/api/v1/me/interview-invites/{$invite->token}/start")->assertUnprocessable();

    $candidate = User::query()->find($invite->candidate_user_id);
    Sanctum::actingAs($candidate);
    $start = $this->postJson("/api/v1/me/interview-invites/{$invite->token}/start")->assertOk()->json('data');
    $mock = MockInterview::query()->with('blueprint')->findOrFail($start['mock_id']);
    expect($start['room_url'])->toBe("/job-interview/{$mock->id}/room")
        ->and($mock->kind())->toBe('job')
        ->and($mock->blueprint->employer_job_round_id)->toBe($interview->employer_job_round_id)
        ->and($mock->blueprint->employer_job_id)->toBeNull() // never counts as the JD mock needed to apply
        ->and(MockInterview::query()->ofKind('voice')->whereKey($mock->id)->exists())->toBeFalse()
        ->and($interview->fresh()->status)->toBe(EmployerInterviewStatus::InProgress);

    // Pressing start again resumes the same interview.
    expect($this->postJson("/api/v1/me/interview-invites/{$invite->token}/start")->json('data.mock_id'))->toBe($mock->id);

    // The room finishes and scores it → the round is graded, the invite is done.
    $mock->update(['status' => MockInterview::STATUS_COMPLETED, 'overall_score' => 82, 'scorecard' => [
        'overall' => 82, 'competencies' => [['name' => 'Python', 'score' => 85]], 'actions' => [], 'summary' => 'Strong on pipelines.',
    ]]);
    MockCompleted::dispatch($candidate, $mock->fresh());

    $graded = $interview->fresh();
    expect($graded->status)->toBe(EmployerInterviewStatus::Graded)
        ->and($graded->overall_score)->toBe(82)
        ->and($graded->grading_source)->toBe('voice_interview')
        ->and($graded->dimension_scores)->toBe(['python' => 85])
        ->and($invite->fresh()->status)->toBe('done');
});

it('lets only owners and recruiters send invites, and only in their own workspace', function (): void {
    approveTemplates();
    $manager = User::factory()->for($this->tenant)->create(['user_type' => 'employer']);
    EmployerMember::factory()->for($this->tenant)->hiringManager()->create(['employer_workspace_id' => $this->workspace->id, 'user_id' => $manager->id]);
    Sanctum::actingAs($manager);
    $this->postJson("{$this->base}/invite")->assertForbidden();

    Sanctum::actingAs(User::factory()->for(Tenant::factory()->create())->create(['user_type' => 'employer']));
    $this->postJson("{$this->base}/invite")->assertNotFound();
    expect($this->wa->sent)->toBe([]);
});
