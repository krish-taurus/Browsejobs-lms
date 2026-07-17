<?php

declare(strict_types=1);

use App\Enums\BatchMemberStatus;
use App\Enums\BatchType;
use App\Models\AuditLog;
use App\Models\Batch;
use App\Models\BatchMember;
use App\Models\Course;
use App\Models\InterviewTranscript;
use App\Models\MockBlueprint;
use App\Models\MockInterview;
use App\Models\RealInterviewQuestion;
use App\Models\Tenant;
use App\Models\User;
use App\Services\AI\AiClient;
use App\Services\AI\FakeAiClient;
use App\Support\Interviews\FakeTranscriptionClient;
use App\Support\Interviews\TranscriptionClient;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Http\UploadedFile;
use Laravel\Sanctum\Sanctum;

use function Pest\Laravel\getJson;
use function Pest\Laravel\postJson;

beforeEach(function () {
    $this->fake = new FakeAiClient;
    app()->instance(AiClient::class, $this->fake);
    $this->tenant = Tenant::factory()->create();
    $this->seed(RolePermissionSeeder::class);
});

/** A staff placement officer in the tenant. */
function placementOfficerIn(Tenant $tenant): User
{
    $user = User::factory()->for($tenant)->create(['user_type' => 'staff']);
    $user->assignRole('placement-officer');

    return $user;
}

/** A strict-JSON parse reply for the fake AI client. */
function parseReply(array $questions, string $outcome = 'next_round'): string
{
    return json_encode(['outcome' => $outcome, 'questions' => $questions], JSON_THROW_ON_ERROR);
}

function sqlQuestion(array $overrides = []): array
{
    return array_merge([
        'question' => 'How would you optimise a slow SQL join?',
        'normalized' => 'optimise slow sql join',
        'topics' => ['sql optimisation'],
        'difficulty' => 'medium',
        'round' => 'technical',
        'follow_ups' => ['What about indexes?'],
        'strong_answer' => 'Covers explain plans, join order, indexing.',
        'struggle' => null,
        'confidence' => 'high',
    ], $overrides);
}

/* ---- Ingestion: text ---- */

it('ingests a pasted transcript with consent, parses it into pending questions, and audit-logs the upload', function () {
    $officer = placementOfficerIn($this->tenant);
    $this->fake->reply = parseReply([sqlQuestion(), sqlQuestion([
        'question' => 'Explain watermarking for late data.',
        'normalized' => 'watermarking late data',
        'topics' => ['pipelines & orchestration'],
        'confidence' => 'medium',
    ])]);
    Sanctum::actingAs($officer);

    postJson('/api/v1/admin/interview-transcripts', [
        'source' => 'text',
        'consent' => true,
        'role_title' => 'Data Engineer',
        'text' => 'INTERVIEWER: How would you optimise a slow SQL join? CANDIDATE: I would check indexes.',
    ])->assertStatus(202);

    $transcript = InterviewTranscript::withoutGlobalScopes()->sole();
    expect($transcript->status)->toBe(InterviewTranscript::STATUS_PARSED)
        ->and($transcript->questions_found)->toBe(2)
        ->and($transcript->outcome)->toBe('next_round');

    expect(RealInterviewQuestion::withoutGlobalScopes()->where('status', 'pending')->count())->toBe(2)
        ->and(AuditLog::withoutGlobalScopes()->where('action', 'interview_transcript.uploaded')->count())->toBe(1);
});

it('refuses an upload without the consent confirmation', function () {
    Sanctum::actingAs(placementOfficerIn($this->tenant));

    postJson('/api/v1/admin/interview-transcripts', [
        'source' => 'text', 'text' => 'INTERVIEWER: hello.',
    ])->assertStatus(422)->assertJsonValidationErrors('consent');

    expect(InterviewTranscript::withoutGlobalScopes()->count())->toBe(0);
});

it('scrubs PII from the working text and from every parsed field before bank entry', function () {
    $officer = placementOfficerIn($this->tenant);
    // Even if the parser leaks PII, the second scrub pass must catch it.
    $this->fake->reply = parseReply([sqlQuestion([
        'question' => 'Email me at rahul.k@company.com or call +91 98765 43210 about the join.',
        'normalized' => 'email about join',
        'strong_answer' => 'See https://linkedin.com/in/rahul for details.',
    ])]);
    Sanctum::actingAs($officer);

    postJson('/api/v1/admin/interview-transcripts', [
        'source' => 'parakeet',
        'consent' => true,
        'role_title' => 'Data Engineer',
        'text' => 'Candidate rahul.k@company.com (+91 98765 43210) interviewed today.',
    ])->assertStatus(202);

    $transcript = InterviewTranscript::withoutGlobalScopes()->sole();
    expect($transcript->raw_text)->toContain('[EMAIL]')->toContain('[PHONE]')
        ->not->toContain('rahul.k@company.com')->not->toContain('98765');

    $question = RealInterviewQuestion::withoutGlobalScopes()->sole();
    expect($question->question)->toContain('[EMAIL]')->toContain('[PHONE]')
        ->and($question->strong_answer)->toContain('[LINK]')
        ->and($question->question)->not->toContain('rahul.k');
});

it('dedupes repeat questions into asked_count instead of duplicating', function () {
    $officer = placementOfficerIn($this->tenant);
    $this->fake->replies = [parseReply([sqlQuestion()]), parseReply([sqlQuestion()])];
    Sanctum::actingAs($officer);

    foreach ([1, 2] as $i) {
        postJson('/api/v1/admin/interview-transcripts', [
            'source' => 'text', 'consent' => true, 'role_title' => 'Data Engineer',
            'text' => "Transcript number {$i} with the same question.",
        ])->assertStatus(202);
    }

    $question = RealInterviewQuestion::withoutGlobalScopes()->sole();
    expect($question->asked_count)->toBe(2);
});

it('marks the transcript failed when the parser returns garbage', function () {
    $officer = placementOfficerIn($this->tenant);
    $this->fake->reply = 'I am not JSON today.';
    Sanctum::actingAs($officer);

    postJson('/api/v1/admin/interview-transcripts', [
        'source' => 'text', 'consent' => true, 'text' => 'Some interview text here.',
    ])->assertStatus(202);

    $transcript = InterviewTranscript::withoutGlobalScopes()->sole();
    expect($transcript->status)->toBe(InterviewTranscript::STATUS_FAILED)
        ->and($transcript->parse_error)->not->toBeNull()
        ->and(RealInterviewQuestion::withoutGlobalScopes()->count())->toBe(0);
});

/* ---- Ingestion: files ---- */

it('transcribes an audio upload through the swappable client and parses the result', function () {
    app()->instance(TranscriptionClient::class, new FakeTranscriptionClient(
        transcript: 'INTERVIEWER: How would you optimise a slow SQL join? CANDIDATE: Indexes.',
    ));
    $officer = placementOfficerIn($this->tenant);
    $this->fake->reply = parseReply([sqlQuestion()]);
    Sanctum::actingAs($officer);

    postJson('/api/v1/admin/interview-transcripts', [
        'source' => 'audio', 'consent' => true, 'role_title' => 'Data Engineer',
        'file' => UploadedFile::fake()->create('final-round.mp3', 12, 'audio/mpeg'),
    ])->assertStatus(202);

    $transcript = InterviewTranscript::withoutGlobalScopes()->sole();
    expect($transcript->status)->toBe(InterviewTranscript::STATUS_PARSED)
        ->and($transcript->encrypted_path)->not->toBeNull()
        ->and(RealInterviewQuestion::withoutGlobalScopes()->count())->toBe(1);
});

it('fails an audio upload loudly when no transcription provider is configured', function () {
    $officer = placementOfficerIn($this->tenant);
    Sanctum::actingAs($officer);

    postJson('/api/v1/admin/interview-transcripts', [
        'source' => 'audio', 'consent' => true,
        'file' => UploadedFile::fake()->create('call.mp4', 12, 'video/mp4'),
    ])->assertStatus(202);

    $transcript = InterviewTranscript::withoutGlobalScopes()->sole();
    expect($transcript->status)->toBe(InterviewTranscript::STATUS_FAILED)
        ->and($transcript->parse_error)->toContain('No transcription provider')
        ->and($transcript->encrypted_path)->not->toBeNull(); // original preserved
});

it('fans a zip of txt transcripts out into one parsed child per entry', function () {
    $officer = placementOfficerIn($this->tenant);
    $this->fake->replies = [
        parseReply([sqlQuestion()]),
        parseReply([sqlQuestion(['question' => 'Explain star vs snowflake.', 'normalized' => 'star vs snowflake'])]),
    ];
    Sanctum::actingAs($officer);

    $zipPath = tempnam(sys_get_temp_dir(), 'bjl-test').'.zip';
    $zip = new ZipArchive;
    $zip->open($zipPath, ZipArchive::CREATE);
    $zip->addFromString('one.txt', 'First interview transcript text.');
    $zip->addFromString('two.txt', 'Second interview transcript text.');
    $zip->addFromString('ignore.png', 'binary');
    $zip->close();

    postJson('/api/v1/admin/interview-transcripts', [
        'source' => 'zip', 'consent' => true, 'role_title' => 'Data Engineer',
        'file' => new UploadedFile($zipPath, 'batch.zip', 'application/zip', null, true),
    ])->assertStatus(202);

    expect(InterviewTranscript::withoutGlobalScopes()->count())->toBe(3) // parent + 2 children
        ->and(InterviewTranscript::withoutGlobalScopes()->where('status', 'parsed')->count())->toBe(3)
        ->and(RealInterviewQuestion::withoutGlobalScopes()->count())->toBe(2);

    @unlink($zipPath);
});

it('extracts text from a docx upload', function () {
    $officer = placementOfficerIn($this->tenant);
    $this->fake->reply = parseReply([sqlQuestion()]);
    Sanctum::actingAs($officer);

    $docxPath = tempnam(sys_get_temp_dir(), 'bjl-test').'.docx';
    $zip = new ZipArchive;
    $zip->open($docxPath, ZipArchive::CREATE);
    $zip->addFromString('word/document.xml', '<w:document><w:body><w:p><w:r><w:t>INTERVIEWER: How would you optimise a slow SQL join?</w:t></w:r></w:p></w:body></w:document>');
    $zip->close();

    postJson('/api/v1/admin/interview-transcripts', [
        'source' => 'file', 'consent' => true, 'role_title' => 'Data Engineer',
        'file' => new UploadedFile($docxPath, 'debrief.docx', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', null, true),
    ])->assertStatus(202);

    $transcript = InterviewTranscript::withoutGlobalScopes()->sole();
    expect($transcript->status)->toBe(InterviewTranscript::STATUS_PARSED)
        ->and($transcript->raw_text)->toContain('optimise a slow SQL join');

    @unlink($docxPath);
});

it('keeps the encrypted original and fails cleanly for formats it cannot extract', function () {
    Sanctum::actingAs(placementOfficerIn($this->tenant));

    postJson('/api/v1/admin/interview-transcripts', [
        'source' => 'file', 'consent' => true,
        'file' => UploadedFile::fake()->create('scan.pdf', 12, 'application/pdf'),
    ])->assertStatus(202);

    $transcript = InterviewTranscript::withoutGlobalScopes()->sole();
    expect($transcript->status)->toBe(InterviewTranscript::STATUS_FAILED)
        ->and($transcript->parse_error)->toContain('No text extractor')
        ->and($transcript->encrypted_path)->not->toBeNull();
});

/* ---- Permissions + review queue ---- */

it('locks the bank behind manage-placements', function () {
    $student = User::factory()->for($this->tenant)->create(['user_type' => 'student']);
    Sanctum::actingAs($student);
    getJson('/api/v1/admin/interview-bank')->assertForbidden();

    Sanctum::actingAs(counselorIn($this->tenant));
    getJson('/api/v1/admin/interview-bank')->assertForbidden();

    Sanctum::actingAs(placementOfficerIn($this->tenant));
    getJson('/api/v1/admin/interview-bank')->assertOk();
});

it('supports approve, reject, and high-confidence batch-approve in the review queue', function () {
    $officer = placementOfficerIn($this->tenant);
    $high1 = RealInterviewQuestion::factory()->for($this->tenant)->pending()->create();
    $high2 = RealInterviewQuestion::factory()->for($this->tenant)->pending()->create();
    $low = RealInterviewQuestion::factory()->for($this->tenant)->pending()->create(['confidence' => 'low']);
    Sanctum::actingAs($officer);

    postJson("/api/v1/admin/interview-bank/{$low->id}/approve")->assertOk()
        ->assertJsonPath('data.status', 'approved');
    postJson("/api/v1/admin/interview-bank/{$high1->id}/reject")->assertOk()
        ->assertJsonPath('data.status', 'rejected');

    postJson('/api/v1/admin/interview-bank/approve-batch', ['high_confidence' => true])->assertOk()
        ->assertJsonPath('data.approved', 1); // only $high2 is still pending+high

    expect($high2->fresh()->status)->toBe('approved')
        ->and($high2->fresh()->approved_by)->toBe($officer->id);
});

it('reports bank analytics weighted by asked_count', function () {
    RealInterviewQuestion::factory()->for($this->tenant)->create(['asked_count' => 12]);
    RealInterviewQuestion::factory()->for($this->tenant)->create([
        'topic_tags' => ['python'], 'role_title' => 'Data Engineer', 'asked_count' => 4,
    ]);
    RealInterviewQuestion::factory()->for($this->tenant)->pending()->create(['asked_count' => 99]); // pending — excluded
    Sanctum::actingAs(placementOfficerIn($this->tenant));

    getJson('/api/v1/admin/interview-bank')
        ->assertOk()
        ->assertJsonPath('data.analytics.total_approved', 2)
        ->assertJsonPath('data.analytics.by_topic.sql optimisation', 12)
        ->assertJsonPath('data.analytics.by_topic.python', 4)
        ->assertJsonPath('data.analytics.by_role.Data Engineer', 16);
});

it('does not leak another tenant\'s bank into the queue or analytics', function () {
    $other = Tenant::factory()->create();
    RealInterviewQuestion::factory()->for($other)->create(['asked_count' => 50]);
    Sanctum::actingAs(placementOfficerIn($this->tenant));

    getJson('/api/v1/admin/interview-bank')
        ->assertOk()
        ->assertJsonPath('data.analytics.total_approved', 0)
        ->assertJsonCount(0, 'data.approved');
});

/* ---- Bank → mocks + gap report ---- */

it('feeds approved bank questions into the mock interviewer prompt', function () {
    config(['monetization.text_practice_enabled' => true]);
    $course = Course::factory()->for($this->tenant)->create();
    $batch = Batch::factory()->for($this->tenant)->create(['course_id' => $course->id, 'type' => BatchType::Paid->value]);
    $student = User::factory()->for($this->tenant)->create(['user_type' => 'student']);
    BatchMember::factory()->for($this->tenant)->create([
        'batch_id' => $batch->id, 'user_id' => $student->id, 'status' => BatchMemberStatus::Enrolled->value,
    ]);
    MockBlueprint::factory()->for($this->tenant)->create(['course_id' => $course->id, 'role_title' => 'Data Engineer']);

    RealInterviewQuestion::factory()->for($this->tenant)->create([
        'course_id' => $course->id,
        'question' => 'REAL-BANK: How do you tune a skewed join?',
        'asked_count' => 9,
    ]);
    RealInterviewQuestion::factory()->for($this->tenant)->pending()->create([
        'course_id' => $course->id,
        'question' => 'PENDING-ONLY: never ask this.',
    ]);

    $this->fake->reply = 'How do you tune a skewed join?';
    Sanctum::actingAs($student);

    $id = postJson('/api/v1/me/mocks')->assertCreated()->json('data.id');
    postJson("/api/v1/me/mocks/{$id}/answer", ['answer' => 'I would start with the explain plan.'])->assertOk();

    $prompt = $this->fake->calls[0]->user;
    expect($prompt)->toContain('REAL-BANK: How do you tune a skewed join?')
        ->and($prompt)->toContain('REAL QUESTION BANK')
        ->and($prompt)->not->toContain('PENDING-ONLY');
});

it('builds the mock-vs-real gap report from bank weights and the best scorecard', function () {
    config(['monetization.text_practice_enabled' => true]);
    $course = Course::factory()->for($this->tenant)->create();
    $blueprint = MockBlueprint::factory()->for($this->tenant)->create([
        'course_id' => $course->id, 'role_title' => 'Data Engineer',
    ]);
    $student = User::factory()->for($this->tenant)->create(['user_type' => 'student']);

    RealInterviewQuestion::factory()->for($this->tenant)->create(['course_id' => $course->id, 'asked_count' => 12]); // sql optimisation
    RealInterviewQuestion::factory()->for($this->tenant)->create([
        'course_id' => $course->id, 'topic_tags' => ['python'], 'asked_count' => 4,
    ]);

    withinTenant($this->tenant, fn () => MockInterview::query()->create([
        'tenant_id' => $this->tenant->id, 'user_id' => $student->id,
        'mock_blueprint_id' => $blueprint->id, 'mode' => 'text',
        'status' => MockInterview::STATUS_COMPLETED, 'overall_score' => 72,
        'scorecard' => [
            'overall' => 72,
            'competencies' => [['name' => 'SQL & data modelling', 'score' => 55], ['name' => 'Communication', 'score' => 80]],
            'actions' => ['a', 'b', 'c'],
        ],
        'scorecard_source' => 'ai', 'started_at' => now()->subHour(), 'completed_at' => now(),
    ]));

    Sanctum::actingAs($student);

    getJson('/api/v1/me/mocks')
        ->assertOk()
        ->assertJsonPath('data.gap_report.role_title', 'Data Engineer')
        ->assertJsonPath('data.gap_report.items.0.topic', 'sql optimisation')
        ->assertJsonPath('data.gap_report.items.0.real_weight_pct', 75)
        ->assertJsonPath('data.gap_report.items.0.your_score', 55)
        ->assertJsonPath('data.gap_report.items.0.gap', true)
        ->assertJsonPath('data.gap_report.items.1.topic', 'python')
        ->assertJsonPath('data.gap_report.items.1.real_weight_pct', 25)
        ->assertJsonPath('data.gap_report.items.1.your_score', null);
});
