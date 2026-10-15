<?php

declare(strict_types=1);

use App\Actions\Employers\ProposeHiringStep;
use App\Enums\EmployerApplicationStage;
use App\Enums\EmployerInterviewStatus;
use App\Events\EmployerInterviewGraded;
use App\Models\EmployerAgentAction;
use App\Models\EmployerCandidate;
use App\Models\EmployerInterview;
use App\Models\EmployerJob;
use App\Models\EmployerJobApplication;
use App\Models\EmployerMember;
use App\Models\EmployerWhatsappLink;
use App\Models\EmployerWorkspace;
use App\Models\InterviewInvite;
use App\Models\JdMock;
use App\Models\JobShortlistEntry;
use App\Models\Tenant;
use App\Models\User;
use App\Services\AI\AiClient;
use App\Services\AI\FakeAiClient;
use App\Support\Employers\Agent\AgentWords;
use App\Support\Employers\Agent\HiringActionPlanner;
use App\Support\Employers\InterviewTemplates;
use App\Support\Employers\ShortlistSettings;
use App\Support\WhatsApp\FakeWhatsAppClient;
use App\Support\WhatsApp\WhatsAppClient;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Laravel\Sanctum\Sanctum;

beforeEach(function (): void {
    config(['app.frontend_url' => 'https://browsejobs.ai']);
    $this->tenant = Tenant::factory()->create();
    $this->recruiter = User::factory()->for($this->tenant)->create(['user_type' => 'employer', 'name' => 'Meera Iyer']);
    $this->workspace = EmployerWorkspace::factory()->for($this->tenant)->create(['name' => 'Incture']);
    EmployerMember::factory()->for($this->tenant)->create(['employer_workspace_id' => $this->workspace->id, 'user_id' => $this->recruiter->id]);
    $this->job = EmployerJob::factory()->for($this->tenant)->published()->create([
        'employer_workspace_id' => $this->workspace->id, 'created_by_id' => $this->recruiter->id, 'title' => 'Data Engineer',
        'shortlist_settings' => ['confirm_rounds' => true],
    ]);
    JdMock::factory()->for($this->tenant)->ready()->create(['employer_job_id' => $this->job->id]);

    $this->asha = EmployerCandidate::create([
        'tenant_id' => $this->tenant->id, 'employer_workspace_id' => $this->workspace->id, 'source' => 'naukri',
        'dedupe_key' => 'p:9845011111', 'name' => 'Asha Rao', 'phone' => '9845011111', 'skills' => ['python'],
    ]);
    JobShortlistEntry::create([
        'tenant_id' => $this->tenant->id, 'employer_job_id' => $this->job->id, 'source' => 'naukri',
        'employer_candidate_id' => $this->asha->id, 'candidate_key' => 'c:'.$this->asha->id,
        'match_score' => 90, 'rank' => 1, 'reasons' => ['matched_skills' => ['python'], 'missing_skills' => []],
    ]);

    $this->link = EmployerWhatsappLink::create([
        'tenant_id' => $this->tenant->id, 'employer_workspace_id' => $this->workspace->id, 'user_id' => $this->recruiter->id,
        'phone' => '919800000001', 'state' => EmployerWhatsappLink::STATE_IDLE, 'last_inbound_at' => now(),
    ]);
    $this->job->update(['whatsapp_link_id' => $this->link->id]);

    Cache::put('interview_template_statuses', [
        InterviewTemplates::INVITE => 'APPROVED', InterviewTemplates::LINK => 'APPROVED', InterviewTemplates::NEXT_ROUND => 'APPROVED',
    ], 600);

    $this->wa = new FakeWhatsAppClient;
    app()->instance(WhatsAppClient::class, $this->wa);
    $this->ai = new FakeAiClient;
    app()->instance(AiClient::class, $this->ai);
    $this->agentUrl = "/api/v1/employer/workspaces/{$this->workspace->id}/agent";
});

/** What the recruiter typed or said on WhatsApp; returns Taurus's reply to them. */
function recruiterSays(object $test, string $text): string
{
    Artisan::call('employer:whatsapp-inbound', ['from' => '919800000001', '--text' => $text, '--id' => 'wamid.'.uniqid()]);

    return (string) collect($test->wa->sent)->where('to', '919800000001')->last()['body'];
}

/** @param  array<string, mixed>  $fields */
function command(array $fields): string
{
    return (string) json_encode($fields + [
        'intent' => 'act', 'action' => null, 'job' => null, 'round' => null, 'names' => [], 'top_n' => null,
        'from_stage' => null, 'to_stage' => null, 'reason' => null, 'reply' => '',
    ]);
}

/** Someone who has taken and passed L1 and is waiting at the L1 stage. */
function passedL1(object $test, string $name, string $phone, int $score): EmployerJobApplication
{
    $candidate = User::factory()->for($test->tenant)->create(['user_type' => 'student', 'name' => $name, 'phone' => '+91'.$phone]);
    $application = EmployerJobApplication::factory()->for($test->tenant)->create([
        'employer_job_id' => $test->job->id, 'candidate_id' => $candidate->id, 'stage' => EmployerApplicationStage::L1->value,
    ]);
    $interview = EmployerInterview::factory()->for($test->tenant)->create([
        'employer_job_application_id' => $application->id, 'round' => 'l1',
        'status' => EmployerInterviewStatus::Graded->value, 'overall_score' => $score, 'graded_at' => now(),
    ]);
    InterviewInvite::create([
        'tenant_id' => $test->tenant->id, 'employer_job_id' => $test->job->id, 'employer_job_application_id' => $application->id,
        'candidate_user_id' => $candidate->id, 'employer_interview_id' => $interview->id, 'round' => 'l1',
        'phone' => '91'.$phone, 'status' => InterviewInvite::DONE, 'token' => Str::random(40),
    ]);
    EmployerInterviewGraded::dispatch($interview->fresh());

    return $application;
}

it('sends L1 on WhatsApp only after the client says YES', function (): void {
    $this->ai->reply = command(['action' => 'start_round', 'job' => $this->job->id, 'round' => 'l1']);

    $proposal = recruiterSays($this, 'go ahead with L1 for the data engineer role');
    expect($proposal)->toContain('Send L1 interview invites to Asha Rao for *Data Engineer*')
        ->and($proposal)->toContain('Reply *YES*')
        ->and(InterviewInvite::query()->exists())->toBeFalse()
        ->and(EmployerAgentAction::query()->sole()->status)->toBe(EmployerAgentAction::PROPOSED);

    $done = recruiterSays($this, 'Yes');
    $invite = InterviewInvite::query()->sole();
    expect($done)->toContain('Sent L1 invites to 1 candidate for *Data Engineer*')
        ->and($invite->round)->toBe('l1')
        ->and($invite->sent_by_user_id)->toBe($this->recruiter->id)
        ->and(collect($this->wa->sent)->firstWhere('template', InterviewTemplates::INVITE)['to'])->toBe('919845011111')
        ->and(EmployerAgentAction::query()->sole()->status)->toBe(EmployerAgentAction::DONE);

    // A second YES does nothing more: nobody is messaged twice.
    recruiterSays($this, 'yes');
    expect(InterviewInvite::query()->count())->toBe(1)
        ->and(collect($this->wa->sent)->where('template', InterviewTemplates::INVITE))->toHaveCount(1);
});

it('asks first when the shortlist is ready, and NO holds it', function (): void {
    $action = app(ProposeHiringStep::class)->firstRound($this->job);

    $asked = (string) collect($this->wa->sent)->where('to', '919800000001')->last()['body'];
    expect($action->channel)->toBe(EmployerAgentAction::CHANNEL_MILESTONE)
        ->and($asked)->toContain('Send L1 interview invites to Asha Rao')
        ->and($asked)->toContain('Reply YES to send');

    // Proposing again with nothing new neither stacks a second question nor re-asks.
    app(ProposeHiringStep::class)->firstRound($this->job);
    expect(EmployerAgentAction::query()->count())->toBe(1)
        ->and(collect($this->wa->sent)->where('to', '919800000001'))->toHaveCount(1);

    expect(recruiterSays($this, 'not now'))->toContain('held')
        ->and($action->fresh()->status)->toBe(EmployerAgentAction::CANCELLED)
        ->and(InterviewInvite::query()->exists())->toBeFalse()
        // Nothing was asked of the model: a yes or no is never a guess.
        ->and($this->ai->calls)->toBe([]);
});

it('proposes L2 for everyone who cleared L1 instead of inviting them, and sends it on Confirm', function (): void {
    passedL1($this, 'Priya Sharma', '9845022222', 82);
    passedL1($this, 'Rahul Verma', '9845033333', 71);
    passedL1($this, 'Kiran Das', '9845044444', 40); // below the pass mark

    expect(InterviewInvite::query()->where('round', 'l2')->exists())->toBeFalse();
    $action = EmployerAgentAction::query()->where('topic', 'round:l2')->sole();
    expect($action->summary)->toContain('2 people (Priya Sharma (82), Rahul Verma (71))')
        ->and($action->summary)->not->toContain('Kiran');

    Sanctum::actingAs($this->recruiter);
    $this->getJson("{$this->agentUrl}/actions")->assertOk()
        ->assertJsonPath('data.can_act', true)
        ->assertJsonPath('data.open.0.id', $action->id);

    $this->postJson("{$this->agentUrl}/actions/{$action->id}/confirm")->assertOk()
        ->assertJsonPath('data.intent', 'done')
        ->assertJsonPath('data.reply', fn (string $r) => str_contains($r, 'Asked 2 candidates to book the L2 interview'));

    expect(InterviewInvite::query()->where('round', 'l2')->pluck('phone')->sort()->values()->all())->toBe(['919845022222', '919845033333'])
        ->and(collect($this->wa->sent)->where('template', InterviewTemplates::NEXT_ROUND))->toHaveCount(2);

    // Confirming twice runs it once.
    $this->postJson("{$this->agentUrl}/actions/{$action->id}/confirm")->assertOk()
        ->assertJsonPath('data.intent', 'failed')
        ->assertJsonPath('data.reply', 'That has already been done.');
});

it('still books L2 by itself when the client has not asked to approve each round', function (): void {
    $this->job->update(['shortlist_settings' => ['confirm_rounds' => false]]);
    passedL1($this, 'Priya Sharma', '9845022222', 82);

    expect(InterviewInvite::query()->where('round', 'l2')->sole()->phone)->toBe('919845022222')
        ->and(EmployerAgentAction::query()->exists())->toBeFalse();
});

it('makes an offer from the web console when the recruiter says yes out loud', function (): void {
    $priya = passedL1($this, 'Priya Sharma', '9845022222', 82);
    $priya->update(['stage' => EmployerApplicationStage::HumanRound->value]);
    Sanctum::actingAs($this->recruiter);

    $this->ai->reply = command(['action' => 'move_stage', 'names' => ['priya'], 'to_stage' => 'offer']);
    $proposed = $this->postJson("{$this->agentUrl}/command", ['text' => 'make an offer to priya', 'job_id' => $this->job->id])
        ->assertOk()->json('data');
    expect($proposed['intent'])->toBe('proposed')
        ->and($proposed['reply'])->toBe('Move to *Offer*: Priya Sharma for *Data Engineer*. Shall I go ahead?')
        ->and($priya->fresh()->stage)->toBe(EmployerApplicationStage::HumanRound);

    $done = $this->postJson("{$this->agentUrl}/command", ['text' => 'Yes, go ahead.'])->assertOk()->json('data');
    expect($done['intent'])->toBe('done')
        ->and($done['reply'])->toContain('Moved to Offer: Priya Sharma')
        ->and($priya->fresh()->stage)->toBe(EmployerApplicationStage::Offer)
        ->and($priya->transitions()->latest('id')->first()->actor_id)->toBe($this->recruiter->id);
});

it('asks for a reason before rejecting, and asks which person when a name fits two', function (): void {
    passedL1($this, 'Rahul Verma', '9845033333', 71);
    passedL1($this, 'Rahul Nair', '9845055555', 75);
    Sanctum::actingAs($this->recruiter);

    $this->ai->reply = command(['action' => 'reject', 'job' => $this->job->id, 'names' => ['Rahul Verma']]);
    expect($this->postJson("{$this->agentUrl}/command", ['text' => 'reject rahul verma'])->json('data.reply'))
        ->toContain('What reason should I record');

    $this->ai->reply = command(['action' => 'reject', 'job' => $this->job->id, 'names' => ['Rahul'], 'reason' => 'Not enough Kafka experience.']);
    expect($this->postJson("{$this->agentUrl}/command", ['text' => 'reject rahul, not enough kafka'])->json('data.reply'))
        ->toContain('More than one person matches "Rahul"');

    expect(EmployerAgentAction::query()->where('kind', EmployerAgentAction::KIND_REJECT)->exists())->toBeFalse();
});

it('lets a hiring manager ask but not act, and never confirms an expired proposal', function (): void {
    $manager = User::factory()->for($this->tenant)->create(['user_type' => 'employer']);
    EmployerMember::factory()->for($this->tenant)->hiringManager()->create(['employer_workspace_id' => $this->workspace->id, 'user_id' => $manager->id]);
    $action = app(ProposeHiringStep::class)->firstRound($this->job);

    Sanctum::actingAs($manager);
    $this->ai->reply = command(['action' => 'start_round', 'job' => $this->job->id, 'round' => 'l1']);
    expect($this->postJson("{$this->agentUrl}/command", ['text' => 'send L1 now please taurus'])->json('data.reply'))
        ->toContain('Only a workspace owner or recruiter');
    $this->getJson("{$this->agentUrl}/actions")->assertJsonPath('data.can_act', false);
    $this->postJson("{$this->agentUrl}/actions/{$action->id}/confirm")->assertJsonPath('data.intent', 'failed');
    expect($action->fresh()->status)->toBe(EmployerAgentAction::PROPOSED);

    Sanctum::actingAs($this->recruiter);
    $this->travel(HiringActionPlanner::MILESTONE_HOURS + 1)->hours();
    $this->postJson("{$this->agentUrl}/actions/{$action->id}/confirm")->assertJsonPath('data.intent', 'failed')
        ->assertJsonPath('data.reply', fn (string $r) => str_contains($r, 'expired'));
    expect(InterviewInvite::query()->exists())->toBeFalse();

    // Another workspace's proposal is not found.
    $other = EmployerWorkspace::factory()->for($this->tenant)->create();
    EmployerMember::factory()->for($this->tenant)->create(['employer_workspace_id' => $other->id, 'user_id' => $this->recruiter->id]);
    $this->postJson("/api/v1/employer/workspaces/{$other->id}/agent/actions/{$action->id}/confirm")->assertNotFound();
});

it('still drafts a new role on WhatsApp when the message is a job, not an instruction', function (): void {
    $this->ai->replies = [
        command(['intent' => 'post_job']),
        json_encode(['title' => 'Java Developer', 'experience_min_years' => 3, 'experience_max_years' => 6, 'locations' => ['Pune'], 'openings' => 2, 'remote' => false]),
        json_encode(['description' => 'Build our APIs.', 'skills' => ['Java', 'Spring']]),
    ];

    $reply = recruiterSays($this, 'We need 2 Java developers in Pune who can start interviews next week, 3-6 years');
    expect($reply)->toContain('📝 *Job draft*')->and($reply)->toContain('Java Developer');
});

it('reads yes and no the way people say them', function (string $text, bool $yes, bool $no): void {
    expect(AgentWords::isYes($text))->toBe($yes)->and(AgentWords::isNo($text))->toBe($no);
})->with([
    ['YES', true, false],
    ['yes please', true, false],
    ['Yes, go ahead.', true, false],
    ['haan ji', true, false],
    ['ok send them', true, false],
    ['👍', true, false],
    ['yes but only the top 2', false, false],
    ['no', false, true],
    ['Not now', false, true],
    ['hold it please', false, true],
    ['send L1 for the java role', false, false],
]);

it('starts a role posted through Taurus on the web asking before each round', function (): void {
    Sanctum::actingAs($this->recruiter);

    $id = $this->postJson("/api/v1/employer/workspaces/{$this->workspace->id}/jobs", [
        'title' => 'Java Developer', 'description' => 'Build our APIs.', 'skills' => ['Java'],
        'experience_min_years' => 2, 'locations' => ['Pune'], 'openings' => 1, 'confirm_rounds' => true,
    ])->assertCreated()->json('data.id');

    $job = EmployerJob::query()->findOrFail($id);
    expect($job->shortlist_settings['confirm_rounds'])->toBeTrue()
        ->and(ShortlistSettings::forJob($job)->invitesAutomatically())->toBeFalse();

    // Saving the shortlist form, which predates the setting, does not switch it off.
    $this->putJson("/api/v1/employer/workspaces/{$this->workspace->id}/jobs/{$id}/shortlist/settings", [
        'top_n' => 10, 'min_score' => 40, 'sources' => ['browsejobs'], 'auto_on_publish' => true,
    ])->assertOk();
    expect(ShortlistSettings::forJob($job->fresh())->confirmRounds)->toBeTrue();
});

it('moves a named candidate to L2 below the pass mark when the client says so, after YES', function (): void {
    passedL1($this, 'Kunal Shah', '9845055555', 55); // pass mark is 60
    expect(InterviewInvite::query()->where('round', 'l2')->exists())->toBeFalse();

    // "Proceed to L2" names nobody: it says who is below the mark and how to overrule it.
    $this->ai->reply = command(['action' => 'start_round', 'job' => $this->job->id, 'round' => 'l2']);
    $reply = recruiterSays($this, 'Proceed to l2');
    expect($reply)->toContain('Nobody has cleared L1')->and($reply)->toContain('Kunal Shah (55)')->and($reply)->toContain('move Kunal to L2');

    // Naming him is the client's override; it is still a proposal until YES.
    $this->ai->reply = command(['action' => 'start_round', 'job' => $this->job->id, 'round' => 'l2', 'names' => ['kunal']]);
    $proposal = recruiterSays($this, "Let's process kunal to l2");
    expect($proposal)->toContain('Kunal Shah (55)')->and($proposal)->toContain('below the 60 pass mark')->and($proposal)->toContain('Reply *YES*')
        ->and(InterviewInvite::query()->where('round', 'l2')->exists())->toBeFalse();

    expect(recruiterSays($this, 'Yes'))->toContain('Asked 1 candidate to book the L2 interview');
    expect(InterviewInvite::query()->where('round', 'l2')->sole()->phone)->toBe('919845055555');
});
