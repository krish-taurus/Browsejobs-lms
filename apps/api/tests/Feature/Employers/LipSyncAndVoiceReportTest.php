<?php

declare(strict_types=1);

use App\Actions\Employers\BuildInterviewReport;
use App\Actions\Mocks\AnalyseInterviewVoices;
use App\Enums\EmployerApplicationStage;
use App\Enums\EmployerInterviewStatus;
use App\Events\EmployerInterviewGraded;
use App\Listeners\SendInterviewReport;
use App\Models\CvProfile;
use App\Models\EmployerInterview;
use App\Models\EmployerInterviewReport;
use App\Models\EmployerJob;
use App\Models\EmployerJobApplication;
use App\Models\EmployerMember;
use App\Models\EmployerWorkspace;
use App\Models\MockBlueprint;
use App\Models\MockIntegrityEvent;
use App\Models\MockInterview;
use App\Models\MockTurn;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Interviews\VoiceTurns;
use App\Support\WhatsApp\FakeWhatsAppClient;
use App\Support\WhatsApp\WhatsAppClient;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;

beforeEach(function (): void {
    config(['app.frontend_url' => 'https://browsejobs.ai', 'services.elevenlabs.api_key' => 'test-key']);
    Storage::fake('s3');
    Storage::disk('s3')->buildTemporaryUrlsUsing(fn (string $path) => "https://files.test/{$path}?signed=1");
    app()->instance(WhatsAppClient::class, new FakeWhatsAppClient);

    $this->tenant = Tenant::factory()->create();
    $this->recruiter = User::factory()->for($this->tenant)->create(['user_type' => 'employer']);
    $this->workspace = EmployerWorkspace::factory()->for($this->tenant)->create(['name' => 'Incture']);
    EmployerMember::factory()->for($this->tenant)->create(['employer_workspace_id' => $this->workspace->id, 'user_id' => $this->recruiter->id]);
    $this->job = EmployerJob::factory()->for($this->tenant)->published()->create([
        'employer_workspace_id' => $this->workspace->id, 'created_by_id' => $this->recruiter->id, 'title' => 'Data Engineer',
    ]);
    $this->candidate = User::factory()->for($this->tenant)->create(['user_type' => 'student', 'name' => 'Kunal Shah']);
    CvProfile::query()->create(['tenant_id' => $this->tenant->id, 'user_id' => $this->candidate->id, 'data' => ['summary' => 'x', 'skills' => ['Python']]]);
    $this->application = EmployerJobApplication::factory()->for($this->tenant)->create([
        'employer_job_id' => $this->job->id, 'candidate_id' => $this->candidate->id, 'stage' => EmployerApplicationStage::L1->value,
    ]);
    $blueprint = MockBlueprint::query()->create([
        'tenant_id' => $this->tenant->id, 'employer_job_id' => $this->job->id, 'role_title' => 'Data Engineer',
        'competencies' => ['python'], 'opening_question' => 'Hi.', 'is_active' => false,
    ]);
    $this->mock = MockInterview::query()->create([
        'tenant_id' => $this->tenant->id, 'user_id' => $this->candidate->id, 'mock_blueprint_id' => $blueprint->id,
        'mode' => MockInterview::MODE_TEXT, 'is_room' => true, 'status' => MockInterview::STATUS_COMPLETED, 'started_at' => now()->subMinutes(20),
        'ended_at' => now()->subMinute(), 'completed_at' => now(), 'overall_score' => 55,
    ]);
    $this->interview = EmployerInterview::factory()->for($this->tenant)->create([
        'employer_job_application_id' => $this->application->id, 'round' => 'l1', 'mock_interview_id' => $this->mock->id,
        'status' => EmployerInterviewStatus::Graded->value, 'overall_score' => 55, 'graded_at' => now(),
    ]);
    foreach ([['interviewer', 'Walk me through how you would design an ELT pipeline for daily orders.'], ['candidate', 'I would land files in S3 and transform them.']] as [$role, $body]) {
        MockTurn::query()->create(['tenant_id' => $this->tenant->id, 'mock_interview_id' => $this->mock->id, 'role' => $role, 'body' => $body]);
    }
    $this->url = "/api/v1/me/mocks/{$this->mock->id}/integrity";
    $this->mock->update(['status' => MockInterview::STATUS_IN_PROGRESS, 'ended_at' => null, 'completed_at' => null]);
});

/** The room reports a finding with its numbers and a still. */
function reportLip(object $test, string $kind, array $detail, ?int $elapsed = 60, bool $still = true): TestResponse
{
    $fields = ['kind' => $kind, 'detail' => json_encode($detail)] + ($elapsed !== null ? ['elapsed' => $elapsed] : []);
    if ($still) {
        $fields['image'] = UploadedFile::fake()->image('still.jpg', 480, 360);
    }

    return $test->post($test->url, $fields, ['Accept' => 'application/json']);
}

/** Build the report with the PDF captured, so what an employer would read can be checked. */
function captureReport(object $test): array
{
    $captured = [];
    $pdf = Mockery::mock(Barryvdh\DomPDF\PDF::class);
    $pdf->shouldReceive('setPaper')->andReturnSelf();
    $pdf->shouldReceive('setOption')->andReturnSelf();
    $pdf->shouldReceive('output')->andReturn('%PDF-test');
    Pdf::shouldReceive('loadView')->andReturnUsing(function (string $view, array $data) use (&$captured, $pdf) {
        if ($view === 'pdf.interview-report') {
            $captured = $data;
        }

        return $pdf;
    });
    $test->mock->update(['status' => MockInterview::STATUS_COMPLETED, 'completed_at' => now()]);
    app(BuildInterviewReport::class)->handle($test->interview->fresh());

    return $captured;
}

/* ---- What the room may report ---- */

it('records a lip-sync finding with only the numbers its kind may carry', function (): void {
    Sanctum::actingAs($this->candidate);

    reportLip($this, 'lip_lag', ['lag_ms' => 410, 'pct' => 80, 'n' => 9, 'evil' => 'drop table', 'secs' => 3])->assertOk();

    $event = MockIntegrityEvent::query()->sole();
    expect($event->severity)->toBe(MockIntegrityEvent::POSSIBLE)
        ->and($event->detail)->toBe(['lag_ms' => 410, 'pct' => 80, 'n' => 9])
        ->and($event->image_path)->not->toBeNull()
        ->and($event->detailText())->toContain('about 410 ms after the sound');
});

it('keeps one summary, with no photo, and replaces it if sent again', function (): void {
    Sanctum::actingAs($this->candidate);

    reportLip($this, 'lip_sync_summary', ['speech_s' => 60, 'match_pct' => 40, 'lag_ms' => 180, 'onsets' => 8, 'face_pct' => 95, 'flags' => 1], null, true)->assertOk();
    reportLip($this, 'lip_sync_summary', ['speech_s' => 90, 'match_pct' => 91, 'lag_ms' => 120, 'onsets' => 12, 'face_pct' => 98, 'flags' => 0], null, true)->assertOk();

    $summary = MockIntegrityEvent::query()->where('kind', 'lip_sync_summary')->sole();
    expect($summary->detail['match_pct'])->toBe(91)->and($summary->image_path)->toBeNull();
});

it('does not let the browser claim a second voice or a voice check', function (): void {
    Sanctum::actingAs($this->candidate);

    reportLip($this, 'second_voice', ['secs' => 40], 30, false)->assertStatus(422);
    reportLip($this, 'voice_check', ['voices' => 1], 30, false)->assertStatus(422);

    expect(MockIntegrityEvent::query()->count())->toBe(0);
});

it('caps each lip-sync kind at four moments', function (): void {
    Sanctum::actingAs($this->candidate);

    foreach (range(1, 6) as $i) {
        reportLip($this, 'lip_voice_mismatch', ['secs' => 4, 'pct' => 10], $i * 30, false);
    }

    expect(MockIntegrityEvent::query()->where('kind', 'lip_voice_mismatch')->count())->toBe(4);
});

it('refuses lip-sync findings after the interview has ended, and from anyone else', function (): void {
    Sanctum::actingAs(User::factory()->for($this->tenant)->create(['user_type' => 'student']));
    reportLip($this, 'lip_lag', ['lag_ms' => 400], 10, false)->assertNotFound();

    Sanctum::actingAs($this->candidate);
    $this->mock->update(['ended_at' => now()]);
    reportLip($this, 'lip_lag', ['lag_ms' => 400], 10, false)->assertStatus(409);
});

/* ---- What the employer reads ---- */

it('writes a proper lip-sync report when the lips and the voice do not match', function (): void {
    Sanctum::actingAs($this->candidate);
    reportLip($this, 'lip_sync_summary', ['speech_s' => 80, 'match_pct' => 88, 'lag_ms' => 410, 'onsets' => 10, 'face_pct' => 97, 'flags' => 2], null, false)->assertOk();
    reportLip($this, 'lip_lag', ['lag_ms' => 410, 'pct' => 80, 'n' => 10], 125)->assertOk();
    reportLip($this, 'lip_voice_mismatch', ['secs' => 6, 'pct' => 10], 300)->assertOk();
    reportLip($this, 'check_in', [], 40)->assertOk();

    $data = captureReport($this);
    $lip = $data['lipReport'];

    expect($lip['verdict'])->toBe('not_matched')
        ->and($lip['headline'])->toBe('Lip-sync did NOT match the voice')
        ->and(implode(' ', $lip['reasons']))->toContain('410 ms')->and(implode(' ', $lip['reasons']))->toContain('first at 2:05')->and(implode(' ', $lip['reasons']))->toContain('mouth stayed still')
        ->and(collect($lip['stats'])->firstWhere('label', 'Lips behind the voice (median)')['value'])->toBe('410 ms')
        ->and($lip['moments'])->toHaveCount(2)
        ->and($lip['moments'][0]['at'])->toBe('2:05')
        ->and($lip['moments'][0]['link'])->toContain('/api/v1/public/interview-recordings/')->and($lip['moments'][0]['link'])->toEndWith('#t=122')
        ->and($lip['moments'][0]['image'])->not->toBeNull()
        ->and(count($lip['timeline']))->toBe(60)
        ->and(collect($lip['timeline'])->contains('lip'))->toBeTrue()
        // They have their own report: not repeated in the general flag list, and the summary is not a photo.
        ->and(collect($data['flagged'])->pluck('label')->all())->not->toContain('Lips followed the voice (possible lip-sync to another person)')
        ->and($data['checkIns'])->toHaveCount(1)
        ->and($data['possible'])->toBe(2);

    $html = view('pdf.interview-report', $data)->render();
    expect($html)->toContain('Lip-sync report')->and($html)->toContain('Lip-sync did NOT match the voice')->and($html)->toContain('Watch the recording from 2:05')
        ->and($html)->toContain('#t=122');

    // The client sees it first, in the WhatsApp line.
    expect(EmployerInterviewReport::query()->withoutGlobalScopes()->sole()->caption)->toContain('Lip-sync NOT matched (2 moments)');
});

it('says matched only with evidence, and keeps the report short when it did', function (): void {
    Sanctum::actingAs($this->candidate);
    reportLip($this, 'lip_sync_summary', ['speech_s' => 90, 'match_pct' => 93, 'lag_ms' => 120, 'onsets' => 12, 'face_pct' => 98, 'flags' => 0], null, false)->assertOk();
    reportLip($this, 'check_in', [], 40)->assertOk();

    $data = captureReport($this);

    expect($data['lipReport']['verdict'])->toBe('matched')->and($data['lipReport']['moments'])->toBe([]);
    $html = view('pdf.interview-report', $data)->render();
    expect($html)->toContain('Lip-sync matched the voice')->and($html)->not->toContain('Lip-sync report');
    expect(EmployerInterviewReport::query()->withoutGlobalScopes()->sole()->caption)->not->toContain('NOT matched');
});

it('calls a low share of moving lips not matched even with no single moment flagged', function (): void {
    Sanctum::actingAs($this->candidate);
    reportLip($this, 'lip_sync_summary', ['speech_s' => 70, 'match_pct' => 45, 'lag_ms' => 150, 'onsets' => 9, 'face_pct' => 90, 'flags' => 0], null, false)->assertOk();
    reportLip($this, 'check_in', [], 40)->assertOk();

    $lip = captureReport($this)['lipReport'];

    expect($lip['verdict'])->toBe('not_matched')->and(implode(' ', $lip['reasons']))->toContain('only 45%')->and($lip['moments'])->toBe([]);
});

it('is inconclusive, not a pass, when there was too little speech to compare', function (): void {
    Sanctum::actingAs($this->candidate);
    reportLip($this, 'lip_sync_summary', ['speech_s' => 3, 'match_pct' => null, 'lag_ms' => null, 'onsets' => 1, 'face_pct' => 80, 'flags' => 0], null, false)->assertOk();
    reportLip($this, 'check_in', [], 40)->assertOk();

    $data = captureReport($this);

    expect($data['lipReport']['verdict'])->toBe('inconclusive')->and($data['lipReport']['headline'])->toBe('Lip-sync check was inconclusive');
});

it('includes a second voice in the lip-sync report', function (): void {
    Sanctum::actingAs($this->candidate);
    reportLip($this, 'check_in', [], 40)->assertOk();
    MockIntegrityEvent::query()->withoutGlobalScopes()->create([
        'tenant_id' => $this->tenant->id, 'mock_interview_id' => $this->mock->id, 'kind' => 'second_voice', 'severity' => MockIntegrityEvent::POSSIBLE,
        'elapsed_seconds' => 95, 'occurred_at' => now(), 'detail' => ['secs' => 40, 'voices' => 2],
    ]);
    MockIntegrityEvent::query()->withoutGlobalScopes()->create([
        'tenant_id' => $this->tenant->id, 'mock_interview_id' => $this->mock->id, 'kind' => 'voice_check', 'severity' => MockIntegrityEvent::INFO,
        'occurred_at' => now(), 'detail' => ['voices' => 2, 'secs_a' => 60, 'secs_b' => 40],
    ]);

    $lip = captureReport($this)['lipReport'];

    expect($lip['verdict'])->toBe('not_matched')->and(implode(' ', $lip['reasons']))->toContain('A second voice answered from 1:35')
        ->and(collect($lip['stats'])->firstWhere('label', 'Voices answering')['value'])->toBe('2')
        ->and($lip['moments'][0]['label'])->toBe('A second voice answered');
});

it('says "not captured", never "clean", when the lip-sync check did not run', function (): void {
    Sanctum::actingAs($this->candidate);
    reportLip($this, 'check_in', [], 40)->assertOk();

    $data = captureReport($this);

    expect($data['lipSync']['captured'])->toBeFalse()
        ->and($data['lipSync']['line'])->toContain('not captured')->and($data['lipSync']['line'])->toContain('neither passed nor failed')
        ->and($data['voiceCheck']['checked'])->toBeFalse()
        ->and($data['lipReport']['verdict'])->toBe('not_captured');
    expect(view('pdf.interview-report', $data)->render())->toContain('Lip-sync not captured')->and(view('pdf.interview-report', $data)->render())->toContain('Voice not checked');
});

/* ---- The voice check ---- */

function wordsFor(string $speaker, string $text, float $start, float $step = 0.4): array
{
    $out = [];
    foreach (explode(' ', $text) as $i => $word) {
        $out[] = ['text' => $word, 'start' => $start + $i * $step, 'end' => $start + $i * $step + 0.35, 'type' => 'word', 'speaker_id' => $speaker, 'speaker' => $speaker];
    }

    return $out;
}

it('finds the interviewer by the wording of the questions and counts the voices that answered', function (): void {
    $question = 'walk me through how you would design an elt pipeline for daily orders and tell me where you would build in data quality checks';
    $answer = 'i would land the files in s3 then use glue to transform them and load redshift for the analysts every morning before nine';
    $helper = 'actually the better approach is to use a spark job on databricks with delta tables and schedule it from airflow with retries and alerts so that the whole thing runs every morning without anyone touching it';

    $words = [
        ...wordsFor('speaker_0', $question, 0.0),
        ...wordsFor('speaker_1', $answer, 15.0),
        ...wordsFor('speaker_0', 'thank you now explain a data lake versus a data warehouse', 40.0),
        ...wordsFor('speaker_2', $helper, 50.0),
    ];

    $result = VoiceTurns::analyse($words, [$question, 'thank you now explain a data lake versus a data warehouse']);

    expect($result['interviewer'])->toBe('speaker_0')
        ->and($result['voices'])->toBe(2)
        ->and($result['first_b_at'])->toBe(50)
        ->and($result['secs_a'])->toBeGreaterThanOrEqual(8)
        ->and($result['secs_b'])->toBeGreaterThanOrEqual(8);
});

it('counts one voice when only one person answered, and ignores stray words', function (): void {
    $question = 'walk me through how you would design an elt pipeline for daily orders and tell me where you would build in checks';
    $answer = 'i would land the files in s3 then use glue to transform them and load redshift for the analysts every morning before nine';

    $words = [
        ...wordsFor('speaker_0', $question, 0.0),
        ...wordsFor('speaker_1', $answer, 15.0),
        ...wordsFor('speaker_2', 'okay yes', 45.0), // a cough or a stray word, not a person
    ];

    expect(VoiceTurns::analyse($words, [$question])['voices'])->toBe(1);
});

it('records the voice check and a second-voice finding from the recording', function (): void {
    $path = "mock-recordings/{$this->tenant->id}/{$this->mock->id}.webm";
    Storage::disk('s3')->put($path, 'audio');
    $this->mock->update(['recording_url' => $path]);
    $question = 'Walk me through how you would design an ELT pipeline for daily orders.';
    $words = [
        ...wordsFor('speaker_0', 'walk me through how you would design an elt pipeline for daily orders and where you would add checks', 0.0),
        ...wordsFor('speaker_1', 'i would land the files in s3 then use glue to transform them and load redshift for the analysts and we check row counts and nulls before anything is published to the reporting tables', 15.0),
        ...wordsFor('speaker_2', 'actually the better approach is a spark job on databricks with delta tables scheduled from airflow with retries and alerts so that the whole thing runs every morning without anyone touching it', 45.0),
    ];
    $sentBody = '';
    Http::fake(function ($request) use ($words, &$sentBody) {
        $sentBody = (string) $request->body(); // read while the upload stream is still open

        return Http::response(['words' => $words]);
    });

    expect(app(AnalyseInterviewVoices::class)->handle($this->mock))->toBeTrue();

    $check = MockIntegrityEvent::query()->withoutGlobalScopes()->where('kind', 'voice_check')->sole();
    $second = MockIntegrityEvent::query()->withoutGlobalScopes()->where('kind', 'second_voice')->sole();
    expect($check->detail['voices'])->toBe(2)
        ->and($second->severity)->toBe(MockIntegrityEvent::POSSIBLE)
        ->and($second->elapsed_seconds)->toBe(45)
        ->and($second->detailText())->toContain('A second voice');
    expect($sentBody)->toContain('diarize')->and($sentBody)->toContain('word');

    // Run again: nothing is added twice and the service is not called again.
    Http::fake();
    expect(app(AnalyseInterviewVoices::class)->handle($this->mock))->toBeTrue();
    Http::assertNothingSent();
    expect(MockIntegrityEvent::query()->withoutGlobalScopes()->where('kind', 'voice_check')->count())->toBe(1);
});

it('records nothing when the recording or the service is not there', function (): void {
    // No recording yet.
    expect(app(AnalyseInterviewVoices::class)->handle($this->mock))->toBeFalse();

    $path = "mock-recordings/{$this->tenant->id}/{$this->mock->id}.webm";
    Storage::disk('s3')->put($path, 'audio');
    $this->mock->update(['recording_url' => $path]);
    Http::fake(['api.elevenlabs.io/*' => Http::response(['detail' => 'quota'], 429)]);

    expect(app(AnalyseInterviewVoices::class)->handle($this->mock))->toBeFalse()
        ->and(MockIntegrityEvent::query()->withoutGlobalScopes()->count())->toBe(0);
});

/* ---- The report waits for the recording ---- */

it('holds the report back for a recording that is still being joined, then builds it with the voice check', function (): void {
    $this->mock->update(['status' => MockInterview::STATUS_COMPLETED, 'ended_at' => now()->subMinute(), 'completed_at' => now()]);
    $listener = app(SendInterviewReport::class);

    // The recording has not been joined yet: no report, so the voice check can still listen to it.
    $listener->handle(new EmployerInterviewGraded($this->interview->fresh()));
    expect(EmployerInterviewReport::query()->count())->toBe(0);

    // It lands; the next try builds the report with the voice check inside.
    $path = "mock-recordings/{$this->tenant->id}/{$this->mock->id}.webm";
    Storage::disk('s3')->put($path, 'audio');
    $this->mock->update(['recording_url' => $path]);
    Http::fake(['api.elevenlabs.io/*' => Http::response(['words' => wordsFor('speaker_1', 'i would land the files in s3 then use glue to transform them and load redshift for the analysts every day', 5.0)])]);
    $listener->handle(new EmployerInterviewGraded($this->interview->fresh()));

    expect(EmployerInterviewReport::query()->count())->toBe(1)
        ->and(MockIntegrityEvent::query()->withoutGlobalScopes()->where('kind', 'voice_check')->exists())->toBeTrue();
});

it('does not hold the report for an interview that ended long ago with no recording', function (): void {
    $this->mock->update(['status' => MockInterview::STATUS_COMPLETED, 'ended_at' => now()->subMinutes(40), 'completed_at' => now()->subMinutes(39)]);

    app(SendInterviewReport::class)->handle(new EmployerInterviewGraded($this->interview->fresh()));

    expect(EmployerInterviewReport::query()->count())->toBe(1);
});
