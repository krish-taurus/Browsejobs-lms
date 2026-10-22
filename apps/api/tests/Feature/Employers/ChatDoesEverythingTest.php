<?php

declare(strict_types=1);

use App\Enums\EmployerApplicationStage;
use App\Enums\EmployerInterviewStatus;
use App\Models\EmployerAgentAction;
use App\Models\EmployerInterview;
use App\Models\EmployerInterviewReport;
use App\Models\EmployerJob;
use App\Models\EmployerJobApplication;
use App\Models\EmployerMember;
use App\Models\EmployerWhatsappLink;
use App\Models\EmployerWorkspace;
use App\Models\InterviewInvite;
use App\Models\JdMock;
use App\Models\MockBlueprint;
use App\Models\MockInterview;
use App\Models\Tenant;
use App\Models\User;
use App\Services\AI\AiClient;
use App\Services\AI\FakeAiClient;
use App\Support\Employers\Agent\WhatsAppChoice;
use App\Support\Employers\HiringChatAgent;
use App\Support\WhatsApp\FakeWhatsAppClient;
use App\Support\WhatsApp\WhatsAppClient;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;

beforeEach(function (): void {
    config(['app.frontend_url' => 'https://browsejobs.ai', 'ai.providers.openai.api_key' => 'sk-chat-test-0001']);
    Storage::fake('s3');
    Storage::disk('s3')->buildTemporaryUrlsUsing(fn (string $path) => "https://files.test/{$path}?signed=1");
    $this->wa = new FakeWhatsAppClient;
    app()->instance(WhatsAppClient::class, $this->wa);
    $this->ai = new FakeAiClient;
    app()->instance(AiClient::class, $this->ai);

    $this->tenant = Tenant::factory()->create();
    $this->owner = User::factory()->for($this->tenant)->create(['user_type' => 'employer', 'name' => 'Meera Iyer']);
    $this->workspace = EmployerWorkspace::factory()->for($this->tenant)->create(['name' => 'Incture']);
    EmployerMember::factory()->for($this->tenant)->create(['employer_workspace_id' => $this->workspace->id, 'user_id' => $this->owner->id]);
    $this->link = EmployerWhatsappLink::create([
        'tenant_id' => $this->tenant->id, 'employer_workspace_id' => $this->workspace->id, 'user_id' => $this->owner->id,
        'phone' => '919800000001', 'state' => EmployerWhatsappLink::STATE_IDLE, 'last_inbound_at' => now(),
    ]);
    $this->job = EmployerJob::factory()->for($this->tenant)->published()->create([
        'employer_workspace_id' => $this->workspace->id, 'created_by_id' => $this->owner->id, 'title' => 'Data Engineer',
    ]);
    JdMock::factory()->for($this->tenant)->ready()->create(['employer_job_id' => $this->job->id]);

    // Kunal took L1, scored 28, and has a report and a recording.
    $candidate = User::factory()->for($this->tenant)->create(['user_type' => 'student', 'name' => 'Kunal Shah', 'phone' => '+919845055555']);
    $application = EmployerJobApplication::factory()->for($this->tenant)->create([
        'employer_job_id' => $this->job->id, 'candidate_id' => $candidate->id, 'stage' => EmployerApplicationStage::L1->value,
    ]);
    $blueprint = MockBlueprint::query()->create([
        'tenant_id' => $this->tenant->id, 'employer_job_id' => $this->job->id, 'role_title' => 'Data Engineer',
        'competencies' => ['python'], 'opening_question' => 'Hi.', 'is_active' => false,
    ]);
    $mock = MockInterview::query()->create([
        'tenant_id' => $this->tenant->id, 'user_id' => $candidate->id, 'mock_blueprint_id' => $blueprint->id,
        'mode' => MockInterview::MODE_VOICE, 'status' => MockInterview::STATUS_COMPLETED, 'started_at' => now()->subHour(),
        'overall_score' => 28, 'recording_url' => 'mock-recordings/kunal.webm',
    ]);
    $interview = EmployerInterview::factory()->for($this->tenant)->create([
        'employer_job_application_id' => $application->id, 'round' => 'l1', 'mock_interview_id' => $mock->id,
        'status' => EmployerInterviewStatus::Graded->value, 'overall_score' => 28, 'graded_at' => now()->subHour(),
    ]);
    InterviewInvite::create([
        'tenant_id' => $this->tenant->id, 'employer_job_id' => $this->job->id, 'employer_job_application_id' => $application->id,
        'candidate_user_id' => $candidate->id, 'employer_interview_id' => $interview->id, 'round' => 'l1',
        'phone' => '919845055555', 'status' => InterviewInvite::DONE, 'token' => Str::random(40), 'chosen_date' => now()->subDay(),
    ]);
    EmployerInterviewReport::query()->create([
        'tenant_id' => $this->tenant->id, 'employer_interview_id' => $interview->id, 'employer_job_id' => $this->job->id,
        'pdf_path' => 'interview-reports/kunal.pdf', 'caption' => 'Interview report', 'status' => EmployerInterviewReport::SENT,
    ]);
    $this->url = "/api/v1/employer/workspaces/{$this->workspace->id}/agent/command";
});

/** @param  array<string, mixed>  $fields */
function chatRead(array $fields): string
{
    return (string) json_encode($fields + [
        'intent' => 'act', 'action' => null, 'job' => null, 'round' => null, 'names' => [], 'top_n' => null,
        'from_stage' => null, 'to_stage' => null, 'reason' => null, 'reply' => '',
    ]);
}

it('brings a candidate report and recording as links in the web chat, with no confirmation', function (): void {
    Sanctum::actingAs($this->owner);
    $this->ai->replies = [chatRead(['action' => 'send_report', 'names' => ['Kunal'], 'round' => 'l1'])];

    $reply = $this->postJson($this->url, ['text' => 'show Kunal L1 report'])->assertOk()->json('data');

    expect($reply['intent'])->toBe('answered')
        ->and($reply['reply'])->toContain('Kunal Shah: L1 28/100.')
        ->and($reply['reply'])->toContain('Report: https://files.test/interview-reports/kunal.pdf')
        ->and($reply['reply'])->toContain('Recording: ')
        ->and($reply['action'])->toBeNull()
        ->and(EmployerAgentAction::query()->count())->toBe(0);
});

it('says so when there is no report for the person asked about', function (): void {
    Sanctum::actingAs($this->owner);
    $this->ai->replies = [chatRead(['action' => 'send_report', 'names' => ['Priya']])];

    expect($this->postJson($this->url, ['text' => 'Priya report'])->json('data.reply'))
        ->toContain('could not find a report for "Priya"')->toContain('Kunal Shah');
});

it('lets someone retake a round from the web chat, as a proposal to approve', function (): void {
    Sanctum::actingAs($this->owner);
    $this->ai->replies = [chatRead(['action' => 'retake_round', 'names' => ['Kunal'], 'round' => 'l1'])];

    $reply = $this->postJson($this->url, ['text' => 'let Kunal retake L1'])->assertOk()->json('data');

    expect($reply['intent'])->toBe('proposed')
        ->and($reply['reply'])->toContain('Kunal Shah (28/100 before)')
        ->and($reply['action']['kind'])->toBe('retake_round');
});

it('answers small talk with the floor in view instead of a menu', function (): void {
    Sanctum::actingAs($this->owner);
    $this->ai->replies = [chatRead(['intent' => 'other', 'reply' => 'Hello!']), 'Good morning! Kunal finished L1 with 28 out of 100, so that one needs a look.'];

    $reply = $this->postJson($this->url, ['text' => 'Good morning, how are you?'])->assertOk()->json('data');

    expect($reply['intent'])->toBe('answered')->and($reply['reply'])->toContain('Good morning!');
});

it('treats a greeting on WhatsApp as a person talking, not as a job to post', function (): void {
    expect(HiringChatAgent::isSmallTalk('Good morning'))->toBeTrue()
        ->and(HiringChatAgent::isSmallTalk('kaise ho?'))->toBeTrue()
        ->and(HiringChatAgent::isSmallTalk('कैसे हो'))->toBeTrue()
        ->and(HiringChatAgent::isSmallTalk('What are you doing'))->toBeTrue()
        ->and(HiringChatAgent::isSmallTalk('We need 2 data engineers in Pune'))->toBeFalse();

    $this->ai->replies = [chatRead(['intent' => 'other']), 'Good morning! One interview is waiting to be started.'];
    Artisan::call('employer:whatsapp-inbound', ['from' => '919800000001', '--text' => 'Good morning', '--id' => 'wamid.gm1']);

    $last = collect($this->wa->sent)->where('to', '919800000001')->last()['body'];
    expect($last)->toContain('Good morning!')
        ->and(EmployerJob::query()->count())->toBe(1); // nothing was drafted or posted
});

it('does not swallow a new role as the answer to an open question', function (): void {
    // "send a report" leaves a question open (two roles); a long new request must not be taken for its answer.
    EmployerJob::factory()->for($this->tenant)->published()->create([
        'employer_workspace_id' => $this->workspace->id, 'created_by_id' => $this->owner->id, 'title' => 'Java Developer',
    ]);
    Artisan::call('employer:whatsapp-inbound', ['from' => '919800000001', '--text' => 'send the report', '--id' => 'wamid.q1']);
    expect($this->link->fresh()->choice)->not->toBeNull();

    $this->ai->replies = [
        json_encode(['title' => 'Big Data Engineer', 'experience_min_years' => 3, 'experience_max_years' => null, 'locations' => [], 'openings' => null, 'remote' => true]),
        json_encode(['description' => 'You will build big data pipelines.', 'skills' => ['SQL', 'PySpark', 'Hadoop', 'AWS']]),
    ];
    Artisan::call('employer:whatsapp-inbound', [
        'from' => '919800000001',
        '--text' => 'post a job for big data engineer role with 3 years of experience. Required tools are sql, PYSPARK, Hadoop, AWS, hive, python. Work mode is remote',
        '--id' => 'wamid.q2',
    ]);

    $last = collect($this->wa->sent)->where('to', '919800000001')->last()['body'];
    expect($last)->toContain('Big Data Engineer')->and($last)->not->toContain('I did not get who')
        ->and($this->link->fresh()->choice)->toBeNull()
        ->and($this->link->fresh()->state)->toBe('confirming');
});

it('still takes short answers to an open question, and tells a new request apart', function (): void {
    expect(WhatsAppChoice::startsSomethingNew('1'))->toBeFalse()
        ->and(WhatsAppChoice::startsSomethingNew('1,3'))->toBeFalse()
        ->and(WhatsAppChoice::startsSomethingNew('ALL'))->toBeFalse()
        ->and(WhatsAppChoice::startsSomethingNew('Kunal'))->toBeFalse()
        ->and(WhatsAppChoice::startsSomethingNew('the Data Engineer one'))->toBeFalse()
        ->and(WhatsAppChoice::startsSomethingNew('post a job for java developer'))->toBeTrue()
        ->and(WhatsAppChoice::startsSomethingNew('retake L1'))->toBeTrue()
        ->and(WhatsAppChoice::startsSomethingNew('we need 2 testers in Pune'))->toBeTrue();
});

it('answers a pipeline question and small talk while a job draft waits, without editing the draft', function (): void {
    $this->ai->replies = [
        json_encode(['title' => 'Java Developer', 'experience_min_years' => 2, 'experience_max_years' => null, 'locations' => ['Pune'], 'openings' => 1, 'remote' => false]),
        json_encode(['description' => 'Build services in Java.', 'skills' => ['Java']]),
    ];
    Artisan::call('employer:whatsapp-inbound', ['from' => '919800000001', '--text' => 'need a java developer in pune', '--id' => 'wamid.d1']);
    $before = $this->link->fresh()->draft;
    expect($this->link->fresh()->state)->toBe('confirming');

    $this->ai->replies = [chatRead(['intent' => 'question']), 'One person is in L1 right now.'];
    Artisan::call('employer:whatsapp-inbound', ['from' => '919800000001', '--text' => 'how many people are in L1?', '--id' => 'wamid.d2']);
    $last = collect($this->wa->sent)->where('to', '919800000001')->last()['body'];
    expect($last)->toContain('One person is in L1')->toContain('draft is still waiting')
        ->and($this->link->fresh()->draft)->toEqual($before);

    $this->ai->replies = [chatRead(['intent' => 'other']), 'Good morning! Your draft is ready when you are.'];
    Artisan::call('employer:whatsapp-inbound', ['from' => '919800000001', '--text' => 'good morning', '--id' => 'wamid.d3']);
    expect(collect($this->wa->sent)->where('to', '919800000001')->last()['body'])->toContain('Good morning')
        ->and($this->link->fresh()->draft)->toEqual($before)
        ->and($this->link->fresh()->state)->toBe('confirming');
});
