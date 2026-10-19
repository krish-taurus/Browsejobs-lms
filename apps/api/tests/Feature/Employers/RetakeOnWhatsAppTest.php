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
use App\Support\Employers\InterviewTemplates;
use App\Support\WhatsApp\FakeWhatsAppClient;
use App\Support\WhatsApp\WhatsAppClient;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

beforeEach(function (): void {
    config(['app.frontend_url' => 'https://browsejobs.ai']);
    Storage::fake('s3');
    Storage::disk('s3')->buildTemporaryUrlsUsing(fn (string $path) => "https://files.test/{$path}?signed=1");
    Cache::put('interview_template_statuses', [
        InterviewTemplates::INVITE => 'APPROVED', InterviewTemplates::LINK => 'APPROVED', InterviewTemplates::NEXT_ROUND => 'APPROVED',
    ], 600);

    $this->wa = new FakeWhatsAppClient;
    app()->instance(WhatsAppClient::class, $this->wa);
    app()->instance(AiClient::class, new FakeAiClient);

    $this->tenant = Tenant::factory()->create();
    $this->owner = User::factory()->for($this->tenant)->create(['user_type' => 'employer', 'name' => 'Meera Iyer']);
    $this->workspace = EmployerWorkspace::factory()->for($this->tenant)->create(['name' => 'Incture']);
    EmployerMember::factory()->for($this->tenant)->create(['employer_workspace_id' => $this->workspace->id, 'user_id' => $this->owner->id]);
    $this->link = EmployerWhatsappLink::create([
        'tenant_id' => $this->tenant->id, 'employer_workspace_id' => $this->workspace->id, 'user_id' => $this->owner->id,
        'phone' => '919800000001', 'state' => EmployerWhatsappLink::STATE_IDLE, 'last_inbound_at' => now(),
    ]);

    $this->jobA = retakeJob($this, 'Data Engineer');
    $this->jobB = retakeJob($this, 'Java Developer');
    $this->kunal = retakeCandidate($this, $this->jobA, 'Kunal Shah', '9845055555', 28);
});

function retakeJob(object $test, string $title): EmployerJob
{
    $job = EmployerJob::factory()->for($test->tenant)->published()->create([
        'employer_workspace_id' => $test->workspace->id, 'created_by_id' => $test->owner->id, 'title' => $title,
    ]);
    JdMock::factory()->for($test->tenant)->ready()->create(['employer_job_id' => $job->id]);

    return $job;
}

/** Someone who has taken L1 on a job: an application, an invite, a scored interview with a recording. */
function retakeCandidate(object $test, EmployerJob $job, string $name, string $phone, int $score): EmployerJobApplication
{
    $candidate = User::factory()->for($test->tenant)->create(['user_type' => 'student', 'name' => $name, 'phone' => '+91'.$phone]);
    $application = EmployerJobApplication::factory()->for($test->tenant)->create([
        'employer_job_id' => $job->id, 'candidate_id' => $candidate->id, 'stage' => EmployerApplicationStage::L1->value,
    ]);
    $blueprint = MockBlueprint::query()->create([
        'tenant_id' => $test->tenant->id, 'employer_job_id' => $job->id, 'role_title' => $job->title,
        'competencies' => ['python'], 'opening_question' => 'Hi.', 'is_active' => false,
    ]);
    $mock = MockInterview::query()->create([
        'tenant_id' => $test->tenant->id, 'user_id' => $candidate->id, 'mock_blueprint_id' => $blueprint->id,
        'mode' => MockInterview::MODE_VOICE, 'status' => MockInterview::STATUS_COMPLETED, 'started_at' => now()->subHour(),
        'overall_score' => $score, 'recording_url' => "mock-recordings/{$test->tenant->id}/{$name}.webm",
    ]);
    $interview = EmployerInterview::factory()->for($test->tenant)->create([
        'employer_job_application_id' => $application->id, 'round' => 'l1', 'mock_interview_id' => $mock->id,
        'status' => EmployerInterviewStatus::Graded->value, 'overall_score' => $score, 'graded_at' => now()->subHour(),
        'dimension_scores' => ['python' => $score],
    ]);
    InterviewInvite::create([
        'tenant_id' => $test->tenant->id, 'employer_job_id' => $job->id, 'employer_job_application_id' => $application->id,
        'candidate_user_id' => $candidate->id, 'employer_interview_id' => $interview->id, 'round' => 'l1',
        'phone' => '91'.$phone, 'status' => InterviewInvite::DONE, 'token' => Str::random(40), 'chosen_date' => now()->subDay(),
        'zoom_meeting_id' => null,
    ]);

    return $application;
}

/** What the client typed; returns what Taurus said back to them (the last message sent to that phone). */
function clientSays(object $test, string $text): string
{
    Artisan::call('employer:whatsapp-inbound', ['from' => '919800000001', '--text' => $text, '--id' => 'wamid.'.uniqid()]);

    return (string) collect($test->wa->sent)->where('to', '919800000001')->last()['body'];
}

it('asks which role when the client says retake L1, then shows that role\'s candidates', function (): void {
    $asked = clientSays($this, 'retake L1');
    expect($asked)->toContain('Which role?')->and($asked)->toContain('Data Engineer')->and($asked)->toContain('Java Developer');

    // "1" is an answer, never a new job. The newest role is listed first.
    $list = clientSays($this, '2');
    expect($list)->toContain('*Data Engineer*')->and($list)->toContain('Kunal Shah')->and($list)->toContain('L1 28/100')
        ->and($list)->toContain('below the pass mark of 60')->and($list)->toContain('Who should retake L1?');

    expect(EmployerJob::query()->count())->toBe(2); // nothing was drafted or posted
});

it('lets the client pick the candidate, asks YES, then reopens L1 and keeps the first result', function (): void {
    $oldToken = InterviewInvite::query()->where('employer_job_application_id', $this->kunal->id)->value('token');
    clientSays($this, 'Retake L1');
    clientSays($this, '2');

    $proposal = clientSays($this, '1');
    expect($proposal)->toContain('retake L1')->and($proposal)->toContain('Kunal Shah (28/100 before)')->and($proposal)->toContain('Reply *YES*')
        ->and(EmployerAgentAction::query()->sole()->kind)->toBe(EmployerAgentAction::KIND_RETAKE_ROUND);
    // Nothing moved before the YES.
    expect(EmployerInterview::query()->where('employer_job_application_id', $this->kunal->id)->value('overall_score'))->toBe(28);

    $done = clientSays($this, 'YES');
    expect($done)->toContain('L1 reopened for Kunal Shah');

    $interview = EmployerInterview::query()->where('employer_job_application_id', $this->kunal->id)->sole();
    $invite = InterviewInvite::query()->where('employer_job_application_id', $this->kunal->id)->sole();
    expect($interview->status)->toBe(EmployerInterviewStatus::Invited)
        ->and($interview->overall_score)->toBeNull()
        ->and($interview->mock_interview_id)->toBeNull()
        ->and($interview->previous_attempts[0]['overall_score'])->toBe(28)
        ->and($interview->previous_attempts[0]['mock_interview_id'])->not->toBeNull()
        ->and($invite->token)->not->toBe($oldToken)
        ->and($invite->status)->toBe(InterviewInvite::SENT) // the invite WhatsApp went out
        ->and(collect($this->wa->sent)->firstWhere('template', InterviewTemplates::INVITE)['to'])->toBe('919845055555');

    // The first attempt's interview and recording are still there.
    expect(MockInterview::query()->withoutGlobalScopes()->where('overall_score', 28)->exists())->toBeTrue();
});

it('answers a retake even while a new job draft is waiting for YES, and YES then means the retake', function (): void {
    // The state in the client's chat: a half-made job draft is waiting.
    $this->link->update(['state' => EmployerWhatsappLink::STATE_CONFIRMING, 'draft' => [
        'title' => 'Data Engineer', 'description' => 'x', 'skills' => ['sql'], 'role_family' => null, 'experience_min_years' => null,
        'experience_max_years' => null, 'locations' => [], 'remote' => false, 'openings' => 1, 'request' => 'x', 'at' => now()->subMinutes(5)->toIso8601String(),
    ]]);

    $asked = clientSays($this, 'retake L1');
    expect($asked)->toContain('Which role?')->and($asked)->toContain('job draft is still waiting');

    clientSays($this, '2');
    $proposal = clientSays($this, '1');
    expect($proposal)->toContain('Reply *YES*');

    // The newer question wins: YES runs the retake and the draft stays waiting, unposted.
    clientSays($this, 'yes');
    expect(EmployerAgentAction::query()->sole()->status)->toBe(EmployerAgentAction::DONE)
        ->and(EmployerJob::query()->count())->toBe(2)
        ->and($this->link->fresh()->state)->toBe(EmployerWhatsappLink::STATE_CONFIRMING);
});

it('skips the role question when the message names the role', function (): void {
    $list = clientSays($this, 'let kunal retake the L1 interview for the data engineer role');

    expect($list)->toContain('*Data Engineer*')->and($list)->toContain('Kunal Shah')->and($list)->not->toContain('Which role?');
});

it('asks which round when none was said and the role has more than one', function (): void {
    $asked = clientSays($this, 'I want a retake of the interview for the data engineer role');

    expect($asked)->toContain('Which round?')->and($asked)->toContain('L1');
});

it('accepts a name, all, or a list of numbers when picking candidates', function (): void {
    retakeCandidate($this, $this->jobA, 'Priya Nair', '9845066666', 71);
    clientSays($this, 'retake L1 for the data engineer role');

    expect(clientSays($this, 'priya'))->toContain('Priya Nair (71/100 before)')->and($this->link->fresh()->choice)->toBeNull();

    clientSays($this, 'retake L1 for #'.$this->jobA->id);
    $both = clientSays($this, 'all');
    expect($both)->toContain('Kunal Shah')->and($both)->toContain('Priya Nair');
});

it('says so plainly when nobody has been invited yet', function (): void {
    EmployerJobApplication::query()->where('employer_job_id', $this->jobB->id)->delete();
    clientSays($this, 'retake L1');
    $reply = clientSays($this, '1'); // the Java role, listed first

    expect($reply)->toContain('Nobody has been invited to L1')->and($this->link->fresh()->choice)->toBeNull();
});

it('drops the question on CANCEL and never reads a stray number as a job', function (): void {
    clientSays($this, 'retake L1');

    expect(clientSays($this, 'cancel'))->toContain('dropped it')->and($this->link->fresh()->choice)->toBeNull();
    // With nothing open, a bare number goes back to being an ordinary message.
    expect(EmployerAgentAction::query()->count())->toBe(0);
});

it('stops after three answers it cannot follow', function (): void {
    clientSays($this, 'retake L1');
    clientSays($this, 'banana');
    clientSays($this, 'banana');
    $last = clientSays($this, 'banana');

    expect($last)->toContain('I stopped')->and($this->link->fresh()->choice)->toBeNull();
});

it('never lists another company\'s roles or candidates', function (): void {
    $otherTenant = Tenant::factory()->create();
    $otherOwner = User::factory()->for($otherTenant)->create(['user_type' => 'employer']);
    $otherWorkspace = EmployerWorkspace::factory()->for($otherTenant)->create();
    EmployerJob::factory()->for($otherTenant)->published()->create(['employer_workspace_id' => $otherWorkspace->id, 'created_by_id' => $otherOwner->id, 'title' => 'Secret Role']);

    $asked = clientSays($this, 'retake L1');

    expect($asked)->not->toContain('Secret Role');
});

it('sends a report and the recording link on request, role then candidate', function (): void {
    Storage::disk('s3')->put('reports/kunal.pdf', 'pdf');
    $interview = EmployerInterview::query()->where('employer_job_application_id', $this->kunal->id)->sole();
    EmployerInterviewReport::create([
        'tenant_id' => $this->tenant->id, 'employer_interview_id' => $interview->id, 'employer_job_id' => $this->jobA->id,
        'pdf_path' => 'reports/kunal.pdf', 'caption' => '📄 Kunal Shah · Data Engineer · L1 28/100', 'status' => EmployerInterviewReport::SENT,
        'employer_whatsapp_link_id' => $this->link->id,
    ]);

    $asked = clientSays($this, "send Kunal's report");
    expect($asked)->toContain('Which role?');

    $list = clientSays($this, '2');
    expect($list)->toContain('Kunal Shah')->and($list)->toContain('Whose report do you want?');

    $done = clientSays($this, '1');
    expect($done)->toContain('Sent the report and the recording link for Kunal Shah')
        ->and($this->wa->documents)->toHaveCount(1)
        ->and($this->wa->documents[0]['filename'])->toContain('interview report')
        ->and(collect($this->wa->sent)->pluck('body')->implode("\n"))->toContain('/api/v1/public/interview-recordings/');
});

it('does not treat a normal sentence about reports as a request', function (): void {
    expect(WhatsAppChoice::detect('how many cleared L1?'))->toBeNull()
        ->and(WhatsAppChoice::detect('send the L1 invites'))->toBeNull()
        ->and(WhatsAppChoice::detect('we need 2 data engineers'))->toBeNull()
        ->and(WhatsAppChoice::detect('retake L2'))->toBe(['type' => 'retake', 'round' => 'l2'])
        ->and(WhatsAppChoice::detect('re-take the first round'))->toBe(['type' => 'retake', 'round' => 'l1']);
});
