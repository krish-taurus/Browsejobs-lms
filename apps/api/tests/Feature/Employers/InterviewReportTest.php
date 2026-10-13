<?php

declare(strict_types=1);

use App\Enums\EmployerApplicationStage;
use App\Enums\EmployerInterviewStatus;
use App\Events\EmployerInterviewGraded;
use App\Models\CvProfile;
use App\Models\EmployerInterview;
use App\Models\EmployerInterviewReport;
use App\Models\EmployerJob;
use App\Models\EmployerJobApplication;
use App\Models\EmployerMember;
use App\Models\EmployerWhatsappLink;
use App\Models\EmployerWorkspace;
use App\Models\MockBlueprint;
use App\Models\MockIntegrityEvent;
use App\Models\MockInterview;
use App\Models\MockTurn;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Employers\InterviewTemplates;
use App\Support\WhatsApp\FakeWhatsAppClient;
use App\Support\WhatsApp\WhatsAppClient;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;

beforeEach(function (): void {
    config(['app.frontend_url' => 'https://browsejobs.ai']);
    Storage::fake('s3');
    Storage::disk('s3')->buildTemporaryUrlsUsing(fn (string $path) => "https://files.test/{$path}?signed=1");

    $this->tenant = Tenant::factory()->create();
    $this->recruiter = User::factory()->for($this->tenant)->create(['user_type' => 'employer', 'name' => 'Meera Iyer']);
    $this->workspace = EmployerWorkspace::factory()->for($this->tenant)->create(['name' => 'Incture']);
    EmployerMember::factory()->for($this->tenant)->create(['employer_workspace_id' => $this->workspace->id, 'user_id' => $this->recruiter->id]);
    $this->job = EmployerJob::factory()->for($this->tenant)->published()->create([
        'employer_workspace_id' => $this->workspace->id, 'created_by_id' => $this->recruiter->id, 'title' => 'Data Engineer',
    ]);
    $this->link = EmployerWhatsappLink::create([
        'tenant_id' => $this->tenant->id, 'employer_workspace_id' => $this->workspace->id, 'user_id' => $this->recruiter->id,
        'phone' => '919800000001', 'state' => EmployerWhatsappLink::STATE_IDLE, 'last_inbound_at' => now(),
    ]);
    $this->job->update(['whatsapp_link_id' => $this->link->id]);

    $this->candidate = User::factory()->for($this->tenant)->create(['user_type' => 'student', 'name' => 'Priya Sharma', 'phone' => '+919845022222']);
    CvProfile::query()->create([
        'tenant_id' => $this->tenant->id, 'user_id' => $this->candidate->id,
        'data' => ['summary' => 'Data engineer, 4 years.', 'skills' => ['Python', 'Airflow'], 'experience' => [['title' => 'Data Engineer', 'company' => 'Acme', 'period' => '2022–now', 'bullets' => ['Built pipelines.']]]],
    ]);
    $this->application = EmployerJobApplication::factory()->for($this->tenant)->create([
        'employer_job_id' => $this->job->id, 'candidate_id' => $this->candidate->id, 'stage' => EmployerApplicationStage::L1->value,
    ]);

    $blueprint = MockBlueprint::query()->create([
        'tenant_id' => $this->tenant->id, 'employer_job_id' => $this->job->id, 'role_title' => 'Data Engineer',
        'competencies' => ['python'], 'opening_question' => 'Hi.', 'is_active' => false,
    ]);
    $this->mock = MockInterview::query()->create([
        'tenant_id' => $this->tenant->id, 'user_id' => $this->candidate->id, 'mock_blueprint_id' => $blueprint->id,
        'mode' => MockInterview::MODE_VOICE, 'status' => MockInterview::STATUS_IN_PROGRESS, 'started_at' => now()->subMinutes(20),
    ]);
    $this->interview = EmployerInterview::factory()->for($this->tenant)->create([
        'employer_job_application_id' => $this->application->id, 'round' => 'l1', 'mock_interview_id' => $this->mock->id,
    ]);

    Cache::put('interview_template_statuses', [InterviewTemplates::UPDATE => 'APPROVED'], 600);
    $this->wa = new FakeWhatsAppClient;
    app()->instance(WhatsAppClient::class, $this->wa);
    $this->integrityUrl = "/api/v1/me/mocks/{$this->mock->id}/integrity";
});

function still(): UploadedFile
{
    return UploadedFile::fake()->image('frame.jpg', 480, 360);
}

/** The room flags a few things, then the round is scored. */
function takeAndGrade(object $test, int $score): EmployerInterview
{
    Sanctum::actingAs($test->candidate);
    $test->post($test->integrityUrl, ['kind' => 'check_in', 'elapsed' => 40, 'image' => still()])->assertOk();
    $test->post($test->integrityUrl, ['kind' => 'tab_hidden', 'elapsed' => 95, 'image' => still()])->assertOk();
    $test->post($test->integrityUrl, ['kind' => 'paste', 'elapsed' => 130])->assertOk();
    $test->post($test->integrityUrl, ['kind' => 'multi_face', 'elapsed' => 200, 'image' => still()])->assertOk();

    MockTurn::query()->create(['tenant_id' => $test->tenant->id, 'mock_interview_id' => $test->mock->id, 'role' => 'interviewer', 'body' => 'How would you backfill a month of data?']);
    MockTurn::query()->create(['tenant_id' => $test->tenant->id, 'mock_interview_id' => $test->mock->id, 'role' => 'candidate', 'body' => 'Partition by day and run the DAG with catchup.']);
    $test->mock->update([
        'status' => MockInterview::STATUS_COMPLETED, 'overall_score' => $score, 'completed_at' => now(), 'duration_seconds' => 900,
        'scorecard' => ['strong_moments' => ['Clear on idempotent backfills.'], 'weak_moments' => ['Unsure about cost.']],
    ]);
    $test->interview->update([
        'status' => EmployerInterviewStatus::Graded->value, 'overall_score' => $score, 'graded_at' => now(),
        'dimension_scores' => ['python' => $score + 3, 'communication' => $score - 5], 'grading_summary' => 'Solid pipeline thinking.',
    ]);
    EmployerInterviewGraded::dispatch($test->interview->fresh());

    return $test->interview->fresh();
}

it('records what the room noticed, with a still, only for the candidate\'s own running interview', function (): void {
    Sanctum::actingAs($this->candidate);

    $this->post($this->integrityUrl, ['kind' => 'gaze_down', 'elapsed' => 61, 'image' => still()])->assertOk();
    $event = MockIntegrityEvent::query()->sole();
    expect($event->severity)->toBe(MockIntegrityEvent::POSSIBLE) // decided by the server, never the browser
        ->and($event->elapsed_seconds)->toBe(61)
        ->and(Storage::disk('s3')->exists($event->image_path))->toBeTrue();

    $json = ['Accept' => 'application/json'];
    $this->post($this->integrityUrl, ['kind' => 'made_up'], $json)->assertUnprocessable();
    $this->post($this->integrityUrl, ['kind' => 'paste', 'image' => UploadedFile::fake()->create('x.pdf', 10, 'application/pdf')], $json)->assertUnprocessable();

    Sanctum::actingAs(User::factory()->for($this->tenant)->create(['user_type' => 'student']));
    $this->post($this->integrityUrl, ['kind' => 'paste'], $json)->assertNotFound();

    $this->mock->update(['status' => MockInterview::STATUS_COMPLETED]);
    Sanctum::actingAs($this->candidate);
    $this->post($this->integrityUrl, ['kind' => 'paste'], $json)->assertStatus(409);
    expect(MockIntegrityEvent::query()->count())->toBe(1);
});

it('sends HR the report and the CV on WhatsApp after the round, with the integrity flags', function (): void {
    takeAndGrade($this, 82);

    $report = EmployerInterviewReport::query()->sole();
    expect($report->status)->toBe(EmployerInterviewReport::SENT)
        ->and($report->flags)->toBe(2)
        ->and($report->possible_flags)->toBe(1)
        ->and(Storage::disk('s3')->get($report->pdf_path))->toStartWith('%PDF')
        ->and(Storage::disk('s3')->get($report->cv_path))->toStartWith('%PDF');

    [$pdf, $cv] = $this->wa->documents;
    expect($pdf['to'])->toBe('919800000001')
        ->and($pdf['filename'])->toBe('priya sharma - interview report.pdf')
        ->and($pdf['url'])->toBe("https://files.test/{$report->pdf_path}?signed=1")
        ->and($pdf['caption'])->toContain('Priya Sharma · Data Engineer · L1 82/100 (passed)')
        ->and($pdf['caption'])->toContain('⚠️ 2 integrity flags + 1 possible')
        ->and($cv['filename'])->toBe('priya sharma - CV.pdf');
});

it('reports a failed round too', function (): void {
    takeAndGrade($this, 41);

    expect($this->wa->documents[0]['caption'])->toContain('L1 41/100 (below your pass mark of 60)');
});

it('holds reports outside the 24-hour window, nudges once, and sends them all when HR replies', function (): void {
    $this->link->update(['last_inbound_at' => now()->subDays(2)]);
    takeAndGrade($this, 82);

    // A second candidate's report waits behind the first without a second nudge.
    $other = EmployerInterview::factory()->for($this->tenant)->create([
        'employer_job_application_id' => $this->application->id, 'round' => 'l2', 'status' => EmployerInterviewStatus::Graded->value, 'overall_score' => 77,
    ]);
    EmployerInterviewGraded::dispatch($other);

    expect($this->wa->documents)->toBe([])
        ->and(EmployerInterviewReport::query()->pluck('status')->unique()->all())->toBe([EmployerInterviewReport::PENDING])
        ->and(collect($this->wa->sent)->where('template', InterviewTemplates::UPDATE))->toHaveCount(1)
        ->and(collect($this->wa->sent)->firstWhere('template', InterviewTemplates::UPDATE)['parameters'][2])->toContain('Reply to this message to get the PDF');

    Artisan::call('employer:whatsapp-inbound', ['from' => '919800000001', '--text' => 'ok', '--id' => 'wamid.reply']);

    expect($this->wa->documents)->toHaveCount(3) // L1 report + CV, then the L2 report without repeating the CV
        ->and(EmployerInterviewReport::query()->pluck('status')->unique()->all())->toBe([EmployerInterviewReport::SENT])
        // "ok" only fetched the reports: no confused "I could not read that as a job" reply.
        ->and(collect($this->wa->sent)->whereNull('template'))->toBeEmpty();
});

it('says integrity was not captured rather than clean when the room reported nothing', function (): void {
    $this->interview->update(['status' => EmployerInterviewStatus::Graded->value, 'overall_score' => 70, 'graded_at' => now()]);
    EmployerInterviewGraded::dispatch($this->interview->fresh());

    expect($this->wa->documents[0]['caption'])->toContain('Integrity not captured')
        ->and($this->wa->documents[0]['caption'])->not->toContain('No integrity flags');
});

it('shows the employer the flags with their stills and the report on the candidate page', function (): void {
    takeAndGrade($this, 82);
    Sanctum::actingAs($this->recruiter);

    $round = $this->getJson("/api/v1/employer/workspaces/{$this->workspace->id}/jobs/{$this->job->id}/applications/{$this->application->id}/interviews")
        ->assertOk()->json('data.0');

    expect($round['integrity']['flags'])->toBe(2)
        ->and($round['integrity']['possible'])->toBe(1)
        ->and(collect($round['integrity']['events'])->pluck('kind')->all())->toBe(['check_in', 'tab_hidden', 'paste', 'multi_face'])
        ->and($round['integrity']['events'][1]['image_url'])->toStartWith('https://files.test/mock-integrity/')
        ->and($round['integrity']['events'][2]['image_url'])->toBeNull()
        ->and($round['report_url'])->toStartWith('https://files.test/interview-reports/');
});
