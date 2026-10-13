<?php

declare(strict_types=1);

use App\Enums\EmployerInterviewStatus;
use App\Events\MockCompleted;
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
use App\Models\MockInterview;
use App\Models\Tenant;
use App\Models\User;
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
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
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
    $this->link = EmployerWhatsappLink::query()->create([
        'tenant_id' => $this->tenant->id, 'employer_workspace_id' => $this->workspace->id, 'user_id' => $this->recruiter->id,
        'phone' => '919800000001', 'state' => 'idle', 'last_inbound_at' => now(),
    ]);
    $this->job = EmployerJob::factory()->for($this->tenant)->published()->create([
        'employer_workspace_id' => $this->workspace->id, 'created_by_id' => $this->recruiter->id, 'title' => 'Data Engineer',
        'whatsapp_link_id' => $this->link->id,
    ]);
    JdMock::factory()->for($this->tenant)->ready()->create(['employer_job_id' => $this->job->id]);

    $this->wa = new FakeWhatsAppClient;
    $this->zoom = new FakeZoomClient;
    $this->bots = new FakeMeetingBotClient;
    app()->instance(WhatsAppClient::class, $this->wa);
    app()->instance(ZoomClient::class, $this->zoom);
    app()->instance(MeetingBotClient::class, $this->bots);
    $this->base = "/api/v1/employer/workspaces/{$this->workspace->id}/jobs/{$this->job->id}/shortlist";
});

/** Shortlist one Naukri candidate and send them the L1 invite. */
function zoomCandidate($test, string $name, string $phone): EmployerCandidate
{
    $candidate = EmployerCandidate::create([
        'tenant_id' => $test->tenant->id, 'employer_workspace_id' => $test->workspace->id, 'source' => 'naukri',
        'dedupe_key' => 'p:'.$phone, 'name' => $name, 'phone' => $phone, 'skills' => ['python'],
    ]);
    $entry = JobShortlistEntry::create([
        'tenant_id' => $test->tenant->id, 'employer_job_id' => $test->job->id, 'source' => 'naukri',
        'employer_candidate_id' => $candidate->id, 'candidate_key' => 'c:'.$candidate->id,
        'match_score' => 90, 'rank' => 1, 'reasons' => ['matched_skills' => ['python'], 'missing_skills' => []],
    ]);
    Sanctum::actingAs($test->recruiter);
    $test->postJson("{$test->base}/invite", ['entry_ids' => [$entry->id]])->assertOk();

    return $candidate->fresh();
}

function zoomSays(string $phone, string $text): void
{
    Artisan::call('employer:whatsapp-inbound', ['from' => '91'.$phone, '--text' => $text, '--id' => 'wamid.'.uniqid()]);
}

/** The interviewer page finishes the interview with this score. */
function finishRound(MockInterview $mock, int $score): void
{
    $mock->update(['status' => MockInterview::STATUS_COMPLETED, 'overall_score' => $score, 'completed_at' => now(), 'scorecard' => [
        'overall' => $score, 'competencies' => [['name' => 'Python', 'score' => $score]], 'summary' => 'Clear on pipelines and Spark tuning.',
    ]]);
    MockCompleted::dispatch(User::query()->findOrFail($mock->user_id), $mock->fresh());
}

it('books a Zoom interview the moment a slot is picked, with the AI interviewer bot, and spaces same-day slots', function (): void {
    $asha = zoomCandidate($this, 'Asha Rao', '9845011111');

    $offer = collect($this->wa->sent)->firstWhere('template', InterviewTemplates::INVITE);
    expect(array_slice($offer['parameters'], 3))->toBe(['Tue 13 Oct, 11:00 AM', 'Wed 14 Oct, 11:00 AM', 'Thu 15 Oct, 11:00 AM']);

    zoomSays('9845011111', '2');

    $invite = InterviewInvite::query()->where('candidate_user_id', $asha->user_id)->firstOrFail();
    $start = Carbon::parse('2026-10-14 11:00', 'Asia/Kolkata');
    expect($invite->status)->toBe('scheduled')
        ->and($invite->scheduled_at->equalTo($start))->toBeTrue()
        ->and($invite->zoom_join_url)->toBe($this->zoom->created[0]['join_url'] ?? "https://zoom.test/j/{$invite->zoom_meeting_id}")
        ->and($this->zoom->created[0]['join_before_host'])->toBeTrue()
        ->and($this->zoom->created[0]['start']->equalTo($start))->toBeTrue()
        ->and($this->bots->scheduled[0]['meeting_url'])->toBe($invite->zoom_join_url)
        ->and($this->bots->scheduled[0]['page_url'])->toBe("https://browsejobs.ai/interview-bot/{$invite->bot_code}")
        ->and($this->bots->scheduled[0]['join_at']->equalTo($start->copy()->subMinute()))->toBeTrue()
        ->and($invite->bot_id)->toBe('bot-1');

    // The booking reply, then the Zoom link.
    $toAsha = collect($this->wa->sent)->where('to', '919845011111')->values();
    expect($toAsha[$toAsha->count() - 2]['body'])->toContain('Booked for *Wed 14 Oct, 11:00 AM*')
        ->and($toAsha->last()['body'])->toContain($invite->zoom_join_url)->toContain('AI interviewer');

    // Asking again gets the same link.
    zoomSays('9845011111', 'link please');
    expect(end($this->wa->sent)['body'])->toContain($invite->zoom_join_url);

    // A second candidate on the same day is booked half an hour later (one Zoom licence, one meeting at a time).
    $ravi = zoomCandidate($this, 'Ravi K', '9845022222');
    zoomSays('9845022222', '2');
    $second = InterviewInvite::query()->where('candidate_user_id', $ravi->user_id)->firstOrFail();
    expect($second->slotLabel())->toBe('Wed 14 Oct, 11:30 AM');

    // Asha moves to Thursday: the meeting moves and the bot is booked again.
    zoomSays('9845011111', '3');
    expect($invite->fresh()->slotLabel())->toBe('Thu 15 Oct, 11:00 AM')
        ->and($this->zoom->updated)->toHaveCount(1)
        ->and($this->bots->cancelled)->toBe(['bot-1'])
        ->and($invite->fresh()->bot_id)->toBe('bot-3');
});

it('keeps interviews in the BrowseJobs room when no meeting bot is set up', function (): void {
    $this->bots->on = false;
    zoomCandidate($this, 'Asha Rao', '9845011111');
    zoomSays('9845011111', '1');

    $invite = InterviewInvite::query()->firstOrFail();
    expect($invite->status)->toBe('scheduled')
        ->and($invite->zoom_join_url)->toBeNull()
        ->and($this->zoom->created)->toBe([])
        ->and(end($this->wa->sent)['body'])->toContain('That morning you will get a WhatsApp link');
});

it('runs L1 on Zoom through the bot page, books L2 on a pass, and reports L2 passes to the client', function (): void {
    Storage::fake('s3');
    Http::fake(['recall.test/*' => Http::response('mp4-bytes')]);
    $asha = zoomCandidate($this, 'Asha Rao', '9845011111');
    zoomSays('9845011111', '1');
    $invite = InterviewInvite::query()->firstOrFail();

    // Too early for the bot page; unknown codes are not found.
    $this->postJson("/api/v1/interview-bot/{$invite->bot_code}/start")->assertForbidden();
    $this->postJson('/api/v1/interview-bot/'.str_repeat('x', 48).'/start')->assertNotFound();

    // Slot time: the bot page starts the interview (opening the round if the morning job has not).
    $this->travelTo($invite->scheduled_at);
    $start = $this->postJson("/api/v1/interview-bot/{$invite->bot_code}/start")->assertOk()->json('data');
    $mock = MockInterview::query()->findOrFail($start['mock_id']);
    expect($start)->toMatchArray(['first_name' => 'Asha', 'role' => 'Data Engineer', 'company' => 'Incture', 'round' => 'L1'])
        ->and($mock->user_id)->toBe($asha->user_id)
        ->and($mock->blueprint->employer_job_round_id)->not->toBeNull();

    // The page can read this interview, and nothing else.
    $this->getJson("/api/v1/interview-bot/{$invite->bot_code}/mocks/{$mock->id}")->assertOk();
    $other = MockInterview::query()->create([
        'tenant_id' => $this->tenant->id, 'user_id' => $asha->user_id, 'mock_blueprint_id' => $mock->mock_blueprint_id,
        'mode' => 'text', 'status' => 'in_progress', 'started_at' => now(),
    ]);
    $this->getJson("/api/v1/interview-bot/{$invite->bot_code}/mocks/{$other->id}")->assertNotFound();

    // L1 scored 78: the bot leaves, the Zoom recording is saved, and L2 is offered as slots.
    $this->bots->recordings['bot-1'] = 'https://recall.test/rec.mp4';
    finishRound($mock, 78);
    $l2 = InterviewInvite::query()->where('round', 'l2')->firstOrFail();
    $nextOffer = collect($this->wa->sent)->firstWhere('template', InterviewTemplates::NEXT_ROUND);
    expect($this->bots->left)->toBe(['bot-1'])
        ->and($mock->fresh()->recording_url)->toBe("mock-recordings/{$this->tenant->id}/{$mock->id}-zoom.mp4")
        ->and(Storage::disk('s3')->get($mock->fresh()->recording_url))->toBe('mp4-bytes')
        ->and($l2->status)->toBe('sent')
        ->and(array_slice($nextOffer['parameters'], 0, 4))->toBe(['Asha', 'L1', 'Data Engineer', 'Incture']);

    // L2: book it, take it on Zoom, score 82 → cleared; the client hears about it on WhatsApp.
    zoomSays('9845011111', '1');
    $l2->refresh();
    expect($l2->onZoom())->toBeTrue();
    $this->travelTo($l2->scheduled_at);
    $l2Mock = MockInterview::query()->findOrFail($this->postJson("/api/v1/interview-bot/{$l2->bot_code}/start")->assertOk()->json('data.mock_id'));
    $this->link->update(['last_inbound_at' => now()]); // the client wrote today: the full result goes as a message
    finishRound($l2Mock, 82);

    $application = EmployerJobApplication::query()->where('candidate_id', $asha->user_id)->firstOrFail();
    $toClient = collect($this->wa->sent)->where('to', '919800000001')->last();
    expect($application->stage->value)->toBe('human_round')
        ->and(EmployerInterview::query()->where('employer_job_application_id', $application->id)->where('status', EmployerInterviewStatus::Graded->value)->pluck('overall_score', 'round')->all())
        ->toBe(['l1' => 78, 'l2' => 82])
        ->and($toClient['body'])->toContain('Asha Rao')->toContain('L1 78/100 · L2 82/100')
        ->and($toClient['body'])->toContain("/employer/jobs/{$this->job->id}/candidates/{$application->id}")
        ->and($toClient['body'])->not->toContain('9845011111');
});

it('sends the result template when the client has not written in the last day', function (): void {
    $this->link->update(['last_inbound_at' => now()->subDays(3)]);
    $asha = zoomCandidate($this, 'Asha Rao', '9845011111');
    $rounds = ['l1' => 70, 'l2' => 88];
    foreach ($rounds as $round => $score) {
        zoomSays('9845011111', '1');
        $invite = InterviewInvite::query()->where('round', $round)->firstOrFail();
        $this->travelTo($invite->scheduled_at);
        finishRound(MockInterview::query()->findOrFail($this->postJson("/api/v1/interview-bot/{$invite->bot_code}/start")->json('data.mock_id')), $score);
    }

    $result = collect($this->wa->sent)->firstWhere('template', InterviewTemplates::RESULT);
    expect($result['to'])->toBe('919800000001')
        ->and($result['parameters'])->toBe(['Ravi', 'Asha Rao', 'Data Engineer', '70', '88']);
});

it('does not book L2 below the pass mark', function (): void {
    zoomCandidate($this, 'Asha Rao', '9845011111');
    zoomSays('9845011111', '1');
    $invite = InterviewInvite::query()->firstOrFail();
    $this->travelTo($invite->scheduled_at);
    finishRound(MockInterview::query()->findOrFail($this->postJson("/api/v1/interview-bot/{$invite->bot_code}/start")->json('data.mock_id')), 45);

    expect(InterviewInvite::query()->where('round', 'l2')->exists())->toBeFalse()
        ->and(collect($this->wa->sent)->where('template', InterviewTemplates::NEXT_ROUND))->toBeEmpty();
});

it('cancels the Zoom meeting and the bot when the candidate declines', function (): void {
    zoomCandidate($this, 'Asha Rao', '9845011111');
    zoomSays('9845011111', '1');
    $meetingId = InterviewInvite::query()->value('zoom_meeting_id');

    zoomSays('9845011111', 'not interested');

    expect(InterviewInvite::query()->value('status'))->toBe('declined')
        ->and($this->bots->cancelled)->toBe(['bot-1'])
        ->and($this->zoom->deleted)->toContain($meetingId);
});

it('never lets one tenant\'s bot code open another candidate\'s interview', function (): void {
    zoomCandidate($this, 'Asha Rao', '9845011111');
    zoomSays('9845011111', '1');
    $invite = InterviewInvite::query()->firstOrFail();
    $this->travelTo($invite->scheduled_at);
    $this->postJson("/api/v1/interview-bot/{$invite->bot_code}/start")->assertOk();

    // A mock of a candidate in another tenant is simply not found through this code.
    $otherTenant = Tenant::factory()->create();
    $stranger = User::factory()->for($otherTenant)->create(['user_type' => 'student']);
    $foreign = MockInterview::query()->withoutGlobalScopes()->create([
        'tenant_id' => $otherTenant->id, 'user_id' => $stranger->id, 'mock_blueprint_id' => MockInterview::query()->value('mock_blueprint_id'),
        'mode' => 'text', 'status' => 'in_progress', 'started_at' => now(),
    ]);
    $this->getJson("/api/v1/interview-bot/{$invite->bot_code}/mocks/{$foreign->id}")->assertNotFound();
    $this->postJson("/api/v1/interview-bot/{$invite->bot_code}/mocks/{$foreign->id}/finish")->assertNotFound();
});

it('offers slots at the JD interview time and uses its pass mark', function (): void {
    $this->job->update(['shortlist_settings' => ['interview_time' => '16:30', 'round_pass_mark' => 80]]);
    zoomCandidate($this, 'Asha Rao', '9845011111');

    $offer = collect($this->wa->sent)->firstWhere('template', InterviewTemplates::INVITE);
    expect($offer['parameters'][3])->toBe('Tue 13 Oct, 4:30 PM');

    zoomSays('9845011111', '1');
    $invite = InterviewInvite::query()->firstOrFail();
    $this->travelTo($invite->scheduled_at);
    finishRound(MockInterview::query()->findOrFail($this->postJson("/api/v1/interview-bot/{$invite->bot_code}/start")->json('data.mock_id')), 78);

    expect(InterviewInvite::query()->where('round', 'l2')->exists())->toBeFalse(); // 78 is below this JD's 80
});

/** Did the client's chat get a line containing this (as a message, or inside the update template)? */
function clientSaw(FakeWhatsAppClient $wa, string $needle): bool
{
    return collect($wa->sent)->where('to', '919800000001')->contains(
        fn (array $m) => str_contains((string) $m['body'], $needle) || str_contains(implode(' ', (array) $m['parameters']), $needle)
    );
}

it('tells the client when a candidate books, passes or falls short', function (): void {
    zoomCandidate($this, 'Asha Rao', '9845011111');
    zoomSays('9845011111', '2');
    expect(clientSaw($this->wa, 'Asha Rao booked the L1 AI interview for Wed 14 Oct, 11:00 AM'))->toBeTrue();

    $invite = InterviewInvite::query()->firstOrFail();
    $this->travelTo($invite->scheduled_at);
    finishRound(MockInterview::query()->findOrFail($this->postJson("/api/v1/interview-bot/{$invite->bot_code}/start")->json('data.mock_id')), 74);
    expect(clientSaw($this->wa, 'Asha Rao scored 74/100 in the L1 AI interview and passed'))->toBeTrue();

    // Outside the 24-hour window the client gets the approved update template instead of a message.
    $ravi = zoomCandidate($this, 'Ravi K', '9845022222');
    $this->link->update(['last_inbound_at' => now()->subDays(2)]);
    zoomSays('9845022222', '1');
    $second = InterviewInvite::query()->where('candidate_user_id', $ravi->user_id)->firstOrFail();
    $this->travelTo($second->scheduled_at);
    finishRound(MockInterview::query()->findOrFail($this->postJson("/api/v1/interview-bot/{$second->bot_code}/start")->json('data.mock_id')), 40);
    $update = collect($this->wa->sent)->where('template', InterviewTemplates::UPDATE)->last();
    expect($update['to'])->toBe('919800000001')
        ->and($update['parameters'][0])->toBe('Ravi')
        ->and($update['parameters'][2])->toContain('Ravi K scored 40/100 in the L1 AI interview, below your pass mark of 60');
});

it('reminds the candidate an hour before, nudges once if not started, and tells the client', function (): void {
    $this->bots->on = false; // the BrowseJobs room: the link comes when the round opens that morning
    zoomCandidate($this, 'Asha Rao', '9845011111');
    zoomSays('9845011111', '1'); // Tue 13 Oct, 11:00 AM
    $invite = InterviewInvite::query()->firstOrFail();

    // 9:10 that morning the round opens; she replies OK and gets the link.
    $this->travelTo(Carbon::parse('2026-10-13 09:10', 'Asia/Kolkata'));
    Artisan::call('interviews:open-due');
    zoomSays('9845011111', 'ok');
    expect($invite->fresh()->status)->toBe('open');

    // 10:05: the reminder, with the link (she wrote to us within the day).
    $this->travelTo(Carbon::parse('2026-10-13 10:05', 'Asia/Kolkata'));
    Artisan::call('interviews:remind');
    $reminder = collect($this->wa->sent)->where('to', '919845011111')->last();
    expect($reminder['body'])->toContain('Reminder, Asha')->toContain('11:00 AM')->toContain("/hire-interview/{$invite->token}");

    // Running again sends nothing new.
    $count = count($this->wa->sent);
    Artisan::call('interviews:remind');
    expect($this->wa->sent)->toHaveCount($count);

    // 11:35, not started: one nudge to her, and a line to the client.
    $this->travelTo(Carbon::parse('2026-10-13 11:35', 'Asia/Kolkata'));
    Artisan::call('interviews:remind');
    $nudge = collect($this->wa->sent)->where('to', '919845011111')->last();
    expect($nudge['body'])->toContain('has not been started yet')
        ->and(clientSaw($this->wa, 'Asha Rao has not started the L1 interview booked for Tue 13 Oct, 11:00 AM yet'))->toBeTrue()
        ->and($invite->fresh()->nudged_at)->not->toBeNull();

    $count = count($this->wa->sent);
    Artisan::call('interviews:remind');
    expect($this->wa->sent)->toHaveCount($count);
});
