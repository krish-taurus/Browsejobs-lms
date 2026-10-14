<?php

declare(strict_types=1);

use App\Enums\BatchMemberStatus;
use App\Enums\BatchType;
use App\Enums\EmployerApplicationStage;
use App\Enums\EmployerInterviewStatus;
use App\Models\Batch;
use App\Models\BatchMember;
use App\Models\Course;
use App\Models\CvProfile;
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
use App\Models\MockBlueprint;
use App\Models\MockInterview;
use App\Models\StudentScore;
use App\Models\Tenant;
use App\Models\User;
use App\Services\AI\AiClient;
use App\Services\AI\FakeAiClient;
use App\Support\Employers\HiringChatAgent;
use App\Support\Employers\InterviewTemplates;
use App\Support\Interviews\Bots\FakeMeetingBotClient;
use App\Support\Interviews\Bots\MeetingBotClient;
use App\Support\WhatsApp\FakeWhatsAppClient;
use App\Support\WhatsApp\WhatsAppClient;
use App\Support\Zoom\FakeZoomClient;
use App\Support\Zoom\ZoomClient;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;

beforeEach(function (): void {
    // Monday 9 AM India time: the slots offered are Tue, Wed, Thu.
    $this->travelTo(Carbon::parse('2026-10-12 09:00', 'Asia/Kolkata'));
    config(['app.frontend_url' => 'https://browsejobs.ai']);
    Cache::forever('interview_template_statuses', array_fill_keys(array_keys(InterviewTemplates::BODIES), 'APPROVED'));

    $this->tenant = Tenant::factory()->create();
    $this->recruiter = User::factory()->for($this->tenant)->create(['user_type' => 'employer', 'name' => 'Ravi Kumar']);
    $this->workspace = EmployerWorkspace::factory()->for($this->tenant)->create(['name' => 'Incture']);
    EmployerMember::factory()->for($this->tenant)->create(['employer_workspace_id' => $this->workspace->id, 'user_id' => $this->recruiter->id]);
    $this->link = EmployerWhatsappLink::create([
        'tenant_id' => $this->tenant->id, 'employer_workspace_id' => $this->workspace->id, 'user_id' => $this->recruiter->id,
        'phone' => '919800000001', 'state' => EmployerWhatsappLink::STATE_IDLE, 'last_inbound_at' => now(),
    ]);
    $this->job = EmployerJob::factory()->for($this->tenant)->published()->create([
        'employer_workspace_id' => $this->workspace->id, 'created_by_id' => $this->recruiter->id, 'title' => 'Data Engineer',
        'skills' => ['python', 'sql', 'airflow', 'aws'], 'experience_min_years' => 2, 'experience_max_years' => 6,
        'whatsapp_link_id' => $this->link->id,
    ]);
    JdMock::factory()->for($this->tenant)->ready()->create(['employer_job_id' => $this->job->id]);

    $this->wa = new FakeWhatsAppClient;
    $this->zoom = new FakeZoomClient;
    $this->bots = new FakeMeetingBotClient(on: false); // the BrowseJobs room, unless a test switches Zoom on
    $this->ai = new FakeAiClient;
    app()->instance(WhatsAppClient::class, $this->wa);
    app()->instance(ZoomClient::class, $this->zoom);
    app()->instance(MeetingBotClient::class, $this->bots);
    app()->instance(AiClient::class, $this->ai);
    $this->base = "/api/v1/employer/workspaces/{$this->workspace->id}";
});

/** A trained BrowseJobs student the talent matcher will surface for a Data Engineer JD. */
function autoStudent(object $t, string $name, string $phone, array $skills): User
{
    $student = User::factory()->for($t->tenant)->create(['user_type' => 'student', 'name' => $name, 'phone' => '+91'.$phone]);
    CvProfile::query()->create([
        'tenant_id' => $t->tenant->id, 'user_id' => $student->id,
        'data' => ['skills' => $skills, 'summary' => 'Trained on the BrowseJobs programme.'],
    ]);
    StudentScore::query()->create(['tenant_id' => $t->tenant->id, 'user_id' => $student->id, 'pri' => 60]);
    $course = Course::factory()->for($t->tenant)->create(['name' => 'Data Engineering']);
    $batch = Batch::factory()->for($t->tenant)->create(['course_id' => $course->id, 'type' => BatchType::Paid->value]);
    BatchMember::factory()->for($t->tenant)->create(['batch_id' => $batch->id, 'user_id' => $student->id, 'status' => BatchMemberStatus::Completed->value]);

    return $student;
}

/** A finished AI interview; $fromCv makes it the student's own AI Readiness Interview (built from their CV). */
function autoInterview(User $student, string $role, int $score, bool $fromCv = false): void
{
    $blueprint = MockBlueprint::query()->create([
        'tenant_id' => $student->tenant_id, 'user_id' => $fromCv ? $student->id : null, 'role_title' => $role,
        'competencies' => ['x'], 'opening_question' => 'Hi', 'is_active' => true,
    ]);
    MockInterview::query()->create([
        'tenant_id' => $student->tenant_id, 'user_id' => $student->id, 'mock_blueprint_id' => $blueprint->id,
        'mode' => 'text', 'status' => 'completed', 'overall_score' => $score, 'started_at' => now(), 'completed_at' => now(),
    ]);
}

/** A Naukri candidate on the shortlist who has been sent the L1 invite. */
function autoInvited(object $t, string $name, string $phone): EmployerCandidate
{
    $candidate = EmployerCandidate::create([
        'tenant_id' => $t->tenant->id, 'employer_workspace_id' => $t->workspace->id, 'source' => 'naukri',
        'dedupe_key' => 'p:'.$phone, 'name' => $name, 'phone' => $phone, 'skills' => ['python'],
    ]);
    $entry = JobShortlistEntry::create([
        'tenant_id' => $t->tenant->id, 'employer_job_id' => $t->job->id, 'source' => 'naukri',
        'employer_candidate_id' => $candidate->id, 'candidate_key' => 'c:'.$candidate->id,
        'match_score' => 90, 'rank' => 1, 'reasons' => ['matched_skills' => ['python'], 'missing_skills' => []],
    ]);
    Sanctum::actingAs($t->recruiter);
    $t->postJson("{$t->base}/jobs/{$t->job->id}/shortlist/invite", ['entry_ids' => [$entry->id]])->assertOk();

    return $candidate->fresh();
}

function autoSays(string $phone, string $text): void
{
    Artisan::call('employer:whatsapp-inbound', ['from' => '91'.$phone, '--text' => $text, '--id' => 'wamid.'.uniqid()]);
}

/** What the client types in their hiring chat; returns the reply they get. */
function autoClientSays(object $t, string $text): string
{
    Artisan::call('employer:whatsapp-inbound', ['from' => '919800000001', '--text' => $text, '--id' => 'wamid.'.uniqid()]);

    return (string) collect($t->wa->sent)->where('to', '919800000001')->last()['body'];
}

function autoClientSaw(FakeWhatsAppClient $wa, string $needle): bool
{
    return collect($wa->sent)->where('to', '919800000001')->contains(
        fn (array $m) => str_contains((string) $m['body'], $needle) || str_contains(implode(' ', (array) $m['parameters']), $needle)
    );
}

/** @param  array<string, mixed>  $fields */
function autoCommand(array $fields): string
{
    return (string) json_encode($fields + [
        'intent' => 'act', 'action' => null, 'job' => null, 'round' => null, 'names' => [], 'top_n' => null,
        'from_stage' => null, 'to_stage' => null, 'reason' => null, 'reply' => '',
    ]);
}

/** An application + invite row for a student, in whatever state a test needs. */
function autoInviteRow(object $t, User $student, string $status, array $extra = []): InterviewInvite
{
    $application = EmployerJobApplication::factory()->for($t->tenant)->create([
        'employer_job_id' => $t->job->id, 'candidate_id' => $student->id, 'stage' => EmployerApplicationStage::Shortlisted->value,
    ]);

    return InterviewInvite::create([
        'tenant_id' => $t->tenant->id, 'employer_job_id' => $t->job->id, 'employer_job_application_id' => $application->id,
        'candidate_user_id' => $student->id, 'round' => 'l1', 'phone' => '91'.substr((string) $student->phone, -10),
        'status' => $status, 'token' => Str::random(40), 'date_options' => ['2026-10-13', '2026-10-14', '2026-10-15'],
    ] + $extra);
}

// ---------------------------------------------------------------- who gets shortlisted

it('shortlists a student with a CV and an AI interview first, then a matching CV with none, and invites them all to L1 by itself', function (): void {
    $all = ['python', 'sql', 'airflow', 'aws'];
    $first = autoStudent($this, 'First Fit', '9000000001', $all);
    autoInterview($first, 'Data Engineer', 80);
    $second = autoStudent($this, 'Second Fit', '9000000002', $all);
    autoInterview($second, 'Data Engineer', 50);
    $readiness = autoStudent($this, 'Readiness Fit', '9000000003', $all);
    autoInterview($readiness, 'Backend Developer', 90, fromCv: true); // their AI Readiness Interview counts for any role
    $cvOnly = autoStudent($this, 'Cv Only', '9000000004', $all);
    $weakCv = autoStudent($this, 'Weak Cv', '9000000005', ['python']);
    $lowAi = autoStudent($this, 'Low Ai', '9000000006', $all);
    autoInterview($lowAi, 'Data Engineer', 30);

    Sanctum::actingAs($this->recruiter);
    $data = $this->postJson("{$this->base}/jobs/{$this->job->id}/shortlist/run")->assertOk()->json('data');

    $entries = collect($data['entries']);
    expect($entries->pluck('candidate_user_id')->all())->toBe([$readiness->id, $first->id, $second->id, $cvOnly->id])
        ->and($entries->pluck('reasons.tier')->all())->toBe([1, 1, 2, 3])
        ->and($entries->last()['reasons']['interview_score'])->toBeNull()
        ->and($entries->last()['reasons']['cv_match'])->toBe(100)
        ->and($data['awaiting'])->toBe([])
        ->and($data['settings']['include_cv_only'])->toBeTrue()
        ->and($data['settings']['confirm_rounds'])->toBeFalse();

    // Nobody is asked first: all four get the L1 slot invite, nobody else, and nobody is screened.
    expect(InterviewInvite::query()->where('round', 'l1')->pluck('candidate_user_id')->sort()->values()->all())
        ->toBe(collect([$readiness->id, $first->id, $second->id, $cvOnly->id])->sort()->values()->all())
        ->and(InterviewInvite::query()->where('round', 'screen')->exists())->toBeFalse()
        ->and(collect($this->wa->sent)->where('template', InterviewTemplates::INVITE))->toHaveCount(4)
        ->and(InterviewInvite::query()->whereIn('candidate_user_id', [$weakCv->id, $lowAi->id])->exists())->toBeFalse()
        ->and(EmployerAgentAction::query()->exists())->toBeFalse();
});

// ---------------------------------------------------------------- "I want to do it now"

it('lets a candidate take the interview now: "I want now", or "can we reschedule this for today"', function (): void {
    autoInvited($this, 'Asha Rao', '9845011111');
    autoSays('9845011111', '1'); // booked for tomorrow
    $invite = InterviewInvite::query()->firstOrFail();
    expect($invite->status)->toBe('scheduled');

    autoSays('9845011111', 'Can reschedule this for today');

    $invite->refresh();
    $reply = collect($this->wa->sent)->where('to', '919845011111')->last();
    expect($invite->status)->toBe('open')
        ->and($invite->employer_interview_id)->not->toBeNull()
        ->and($reply['body'])->toContain('Starting your L1 interview now')->toContain("/hire-interview/{$invite->token}")
        ->and(autoClientSaw($this->wa, 'Asha Rao chose to take the L1 AI interview now'))->toBeTrue();

    // Someone who has not even picked a slot can say it too.
    autoInvited($this, 'Ravi K', '9845022222');
    autoSays('9845022222', 'abhi');
    $second = InterviewInvite::query()->where('phone', '919845022222')->firstOrFail();
    expect($second->status)->toBe('open')
        ->and(collect($this->wa->sent)->where('to', '919845022222')->last()['body'])->toContain("/hire-interview/{$second->token}");
});

it('does not treat "not now" as a request to start', function (): void {
    autoInvited($this, 'Asha Rao', '9845011111');
    autoSays('9845011111', 'not now');

    expect(InterviewInvite::query()->firstOrFail()->status)->toBe('declined');
});

it('books tomorrow when they say "tomorrow" or "kal"', function (): void {
    autoInvited($this, 'Asha Rao', '9845011111');
    autoSays('9845011111', 'kal');

    $invite = InterviewInvite::query()->firstOrFail();
    expect($invite->status)->toBe('scheduled')
        ->and($invite->slotLabel())->toBe('Tue 13 Oct, 11:00 AM');
});

it('on Zoom, "now" moves the meeting and the AI interviewer to about ten minutes from now', function (): void {
    $this->bots->on = true;
    $this->travelTo(Carbon::parse('2026-10-12 09:07', 'Asia/Kolkata'));
    autoInvited($this, 'Asha Rao', '9845011111');
    autoSays('9845011111', '1'); // Tue 13 Oct, 11:00 — Zoom meeting and bot booked
    $invite = InterviewInvite::query()->firstOrFail();
    expect($invite->bot_id)->toBe('bot-1');

    autoSays('9845011111', 'I want now');

    $invite->refresh();
    $reply = collect($this->wa->sent)->where('to', '919845011111')->first(fn ($m) => str_contains((string) $m['body'], 'in about 10 minutes'));
    expect($invite->scheduled_at->equalTo(Carbon::parse('2026-10-12 09:17', 'Asia/Kolkata')))->toBeTrue()
        ->and($reply['body'])->toContain('9:17 AM')
        ->and($this->zoom->updated)->toHaveCount(1)
        ->and($this->bots->cancelled)->toBe(['bot-1'])
        ->and($invite->bot_id)->toBe('bot-2')
        ->and(autoClientSaw($this->wa, 'asked to take the L1 AI interview now. It starts at 9:17 AM'))->toBeTrue();
});

it('recognises "do the interview now" from the client, and not a question about interviews', function (): void {
    foreach (['Can we do the interview now', 'interview abhi karwao', 'start L1 today', 'Can we do the interview now?'] as $yes) {
        expect(HiringChatAgent::wantsInterviewNow($yes))->toBeTrue($yes);
    }
    foreach (['how many interviews today?', 'what is the status of the interview', 'send the L1 invites', 'Need 2 data engineers in Pune', 'who cleared L1 today'] as $no) {
        expect(HiringChatAgent::wantsInterviewNow($no))->toBeFalse($no);
    }
});

it('opens the booked interviews now when the client asks, and says who has not booked', function (): void {
    expect(autoClientSays($this, 'Can we do the interview now'))->toContain('Nobody has booked an interview for *Data Engineer* yet');

    autoInvited($this, 'Asha Rao', '9845011111');
    autoSays('9845011111', '2'); // booked for the day after tomorrow
    autoInvited($this, 'Ravi K', '9845022222'); // invited, no slot yet

    $reply = autoClientSays($this, 'Can we do the interview now');

    $invite = InterviewInvite::query()->where('phone', '919845011111')->firstOrFail();
    expect($reply)->toContain('Opened the L1 interview now for Asha Rao')->toContain('1 more is invited but has not picked a slot yet')
        ->and($invite->status)->toBe('open')
        ->and(InterviewInvite::query()->where('phone', '919845022222')->value('status'))->toBe('sent')
        ->and(collect($this->wa->sent)->where('to', '919845011111')->contains(fn ($m) => ($m['template'] ?? null) === InterviewTemplates::LINK))->toBeTrue();
});

// ---------------------------------------------------------------- the client's chat

it('does not stop to ask "which role?" when two roles share a title', function (): void {
    $newer = EmployerJob::factory()->for($this->tenant)->published()->create([
        'employer_workspace_id' => $this->workspace->id, 'created_by_id' => $this->recruiter->id, 'title' => 'Data Engineer',
        'whatsapp_link_id' => $this->link->id,
    ]);
    $candidate = EmployerCandidate::create([
        'tenant_id' => $this->tenant->id, 'employer_workspace_id' => $this->workspace->id, 'source' => 'naukri',
        'dedupe_key' => 'p:9845011111', 'name' => 'Asha Rao', 'phone' => '9845011111', 'skills' => ['python'],
    ]);
    JobShortlistEntry::create([
        'tenant_id' => $this->tenant->id, 'employer_job_id' => $newer->id, 'source' => 'naukri',
        'employer_candidate_id' => $candidate->id, 'candidate_key' => 'c:'.$candidate->id,
        'match_score' => 90, 'rank' => 1, 'reasons' => ['matched_skills' => ['python'], 'missing_skills' => []],
    ]);

    $this->ai->reply = autoCommand(['action' => 'start_round', 'round' => 'l1']);
    $proposal = autoClientSays($this, 'go ahead with L1 for the data engineer role');
    expect($proposal)->not->toContain('Which role')
        ->and($proposal)->toContain('Send L1 interview invites to Asha Rao for *Data Engineer*')
        ->and(EmployerAgentAction::query()->sole()->employer_job_id)->toBe($newer->id);

    // Closing a role is never guessed: it asks, and the roles can be told apart.
    EmployerAgentAction::query()->delete();
    $this->ai->reply = autoCommand(['action' => 'close_job']);
    $question = autoClientSays($this, 'close the data engineer role');
    expect($question)->toContain('Which role?')->toContain("(#{$this->job->id}, posted")->toContain("(#{$newer->id}, posted");
});

// ---------------------------------------------------------------- the monitor

it('flags the agents that have something stuck, in the mission log and on the card', function (): void {
    $failed = autoStudent($this, 'Failed Fiona', '9000000011', ['python']);
    autoInviteRow($this, $failed, InterviewInvite::FAILED, ['error' => 'No mobile number']);
    $silent = autoStudent($this, 'Silent Sam', '9000000012', ['python']);
    autoInviteRow($this, $silent, InterviewInvite::SENT, ['sent_at' => now()->subHours(30)]);
    $late = autoStudent($this, 'Late Lata', '9000000013', ['python']);
    $lateInvite = autoInviteRow($this, $late, InterviewInvite::OPEN, ['scheduled_at' => now()->subHours(2), 'chosen_date' => now()->toDateString()]);
    $interview = EmployerInterview::factory()->for($this->tenant)->create([
        'employer_job_application_id' => $lateInvite->employer_job_application_id, 'round' => 'l1', 'status' => EmployerInterviewStatus::Invited->value,
    ]);
    $lateInvite->update(['employer_interview_id' => $interview->id]);
    $this->workspace->update(['shortlist_test_user_ids' => [$failed->id]]);

    Sanctum::actingAs($this->recruiter);
    $data = $this->getJson("{$this->base}/recruiter-floor")->assertOk()->json('data');

    $agents = collect($data['agents']);
    $log = collect($data['activity'])->where('agent', 'Monitor')->pluck('text')->implode(' | ');
    expect($agents->firstWhere('id', 'invites')['state'])->toBe('approval')
        ->and($agents->firstWhere('id', 'invites')['task'])->toStartWith('⚠')
        ->and($agents->firstWhere('id', 'l1')['state'])->toBe('approval')
        ->and($agents->firstWhere('id', 'l1')['task'])->toContain('Late Lata has not started the L1 interview')
        ->and($log)->toContain('The L1 invite for Failed Fiona failed: No mobile number')
        ->and($log)->toContain('Silent Sam has not replied to the L1 invite for over a day')
        ->and($log)->toContain('Test mode is on: only 1 test student are considered')
        ->and(collect($agents->firstWhere('id', 'invites')['metrics'])->pluck('label')->all())->toContain('Last activity');
});

it('shows nothing wrong when everything is moving', function (): void {
    $fine = autoStudent($this, 'Fine Farah', '9000000014', ['python']);
    autoInviteRow($this, $fine, InterviewInvite::SENT, ['sent_at' => now()->subMinutes(5)]);

    Sanctum::actingAs($this->recruiter);
    $data = $this->getJson("{$this->base}/recruiter-floor")->assertOk()->json('data');

    expect(collect($data['activity'])->where('agent', 'Monitor')->all())->toBe([])
        ->and(collect($data['agents'])->firstWhere('id', 'invites')['state'])->not->toBe('approval');
});

// ---------------------------------------------------------------- keeping it moving

it('asks again, once, with fresh slots, when nobody answered the invite for a day', function (): void {
    $student = autoStudent($this, 'Silent Sam', '9000000012', ['python']);
    $invite = autoInviteRow($this, $student, InterviewInvite::SENT, ['sent_at' => now()->subHours(25), 'date_options' => ['2026-10-10', '2026-10-11', '2026-10-12']]);

    Artisan::call('interviews:remind');

    $invite->refresh();
    $again = collect($this->wa->sent)->where('template', InterviewTemplates::INVITE)->last();
    expect($invite->chased_at)->not->toBeNull()
        ->and($invite->status)->toBe('sent')
        ->and($invite->date_options)->toBe(['2026-10-13', '2026-10-14', '2026-10-15'])
        ->and($again['to'])->toBe('919000000012')
        ->and(array_slice($again['parameters'], 3))->toBe(['Tue 13 Oct, 11:00 AM', 'Wed 14 Oct, 11:00 AM', 'Thu 15 Oct, 11:00 AM'])
        ->and(autoClientSaw($this->wa, 'Silent Sam has not replied to the L1 invite for a day'))->toBeTrue();

    $count = count($this->wa->sent);
    $this->travelTo(now()->addHours(30));
    Artisan::call('interviews:remind');
    expect($this->wa->sent)->toHaveCount($count);
});

it('sends an invite again when it got stuck halfway', function (): void {
    $student = autoStudent($this, 'Stuck Stan', '9000000015', ['python']);
    $invite = autoInviteRow($this, $student, 'sending');
    DB::table('interview_invites')->where('id', $invite->id)->update(['updated_at' => now()->subMinutes(20)]);

    Artisan::call('interviews:remind');

    expect($invite->fresh()->status)->toBe('sent')
        ->and(collect($this->wa->sent)->where('template', InterviewTemplates::INVITE)->last()['to'])->toBe('919000000015');
});
