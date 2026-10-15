<?php

declare(strict_types=1);

use App\Actions\Mocks\FinishMockInterview;
use App\Jobs\AssembleMockRecording;
use App\Jobs\FinishMockInterviewJob;
use App\Models\Batch;
use App\Models\BatchMember;
use App\Models\Course;
use App\Models\MockBlueprint;
use App\Models\MockInterview;
use App\Models\MockTurn;
use App\Models\Tenant;
use App\Models\User;
use App\Services\AI\AiClient;
use App\Services\AI\FakeAiClient;
use App\Support\Mocks\CompetencyCoverage;
use App\Support\Mocks\RecordingLink;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;

use function Pest\Laravel\get;
use function Pest\Laravel\postJson;

beforeEach(function (): void {
    config(['monetization.text_practice_enabled' => true]);
    $this->fake = new FakeAiClient;
    app()->instance(AiClient::class, $this->fake);
    Storage::fake('s3');
    Storage::disk('s3')->buildTemporaryUrlsUsing(fn (string $path) => "https://files.test/{$path}?signed=1");
    $this->tenant = Tenant::factory()->create();
});

/** A data-engineering interview that never touched SQL: the case from the client's report. */
function pipelineInterview(Tenant $tenant, array $competencies = ['ETL', 'SQL', 'Spark', 'Query Optimization']): MockInterview
{
    $course = Course::factory()->for($tenant)->create();
    $batch = Batch::factory()->for($tenant)->create(['course_id' => $course->id]);
    $student = User::factory()->for($tenant)->create(['user_type' => 'student']);
    BatchMember::factory()->for($tenant)->create(['batch_id' => $batch->id, 'user_id' => $student->id]);
    $blueprint = MockBlueprint::factory()->for($tenant)->create([
        'course_id' => $course->id, 'role_title' => 'Data Engineer', 'competencies' => $competencies, 'opening_question' => 'Walk me through an ELT pipeline.',
    ]);
    $mock = MockInterview::query()->create([
        'tenant_id' => $tenant->id, 'user_id' => $student->id, 'mock_blueprint_id' => $blueprint->id,
        'mode' => MockInterview::MODE_TEXT, 'status' => MockInterview::STATUS_IN_PROGRESS, 'started_at' => now()->subMinutes(10),
    ]);
    $turns = [
        ['interviewer', 'Walk me through an ELT pipeline that loads daily orders into the warehouse.'],
        ['candidate', 'I would land the files in S3, crawl them with Glue and transform with a Spark job, then load Redshift.'],
        ['interviewer', 'One Spark job takes six hours. How do you find the bottleneck?'],
        ['candidate', 'Check skew and partitions, then use salting and broadcast joins, and tune the optimisation settings.'],
    ];
    foreach ($turns as [$role, $body]) {
        MockTurn::query()->create(['tenant_id' => $tenant->id, 'mock_interview_id' => $mock->id, 'role' => $role, 'body' => $body]);
    }

    return $mock;
}

function cardClaimingEverything(): string
{
    return json_encode([
        'overall' => 61,
        'competencies' => [
            ['name' => 'ETL', 'asked' => true, 'score' => 62, 'evidence' => 'land the files in S3'],
            ['name' => 'SQL', 'asked' => true, 'score' => 35, 'evidence' => null], // the model invents this one
            ['name' => 'Spark', 'asked' => true, 'score' => 68, 'evidence' => 'salting and broadcast joins'],
            ['name' => 'Query Optimization', 'asked' => true, 'score' => 60, 'evidence' => 'tune the optimisation settings'],
        ],
        'strong_moments' => ['Named salting for skew'],
        'weak_moments' => ['Light on idempotency'],
        'model_answers' => [['question' => 'Q', 'answer' => 'A']],
        'actions' => ['a', 'b', 'c'],
    ], JSON_THROW_ON_ERROR);
}

/* ---- The report only scores what was asked ---- */

it('knows which skills the conversation really touched', function (): void {
    $covered = CompetencyCoverage::check(
        ['ETL', 'SQL', 'Spark', 'Query Optimization', 'Communication', 'Data Modeling'],
        'INTERVIEWER: Build an ELT pipeline. CANDIDATE: I use Spark with salting and the optimisation settings of the job.',
    );

    expect($covered['ETL'])->toBeTrue()           // an ELT pipeline is the same ground
        ->and($covered['SQL'])->toBeFalse()
        ->and($covered['Spark'])->toBeTrue()
        ->and($covered['Query Optimization'])->toBeTrue() // optimisation / optimization
        ->and($covered['Communication'])->toBeTrue()      // nothing distinctive to rule out
        ->and($covered['Data Modeling'])->toBeFalse();
});

it('drops a score the grader invented for a skill nobody was asked about', function (): void {
    $mock = pipelineInterview($this->tenant);
    $this->fake->replies = [cardClaimingEverything()];

    Sanctum::actingAs($mock->student);
    postJson("/api/v1/me/mocks/{$mock->id}/finish")->assertOk()->assertJsonPath('data.scorecard_source', 'ai');

    $card = $mock->fresh()->scorecard;
    $scored = collect($card['competencies'])->pluck('score', 'name')->all();

    expect($scored)->toHaveKey('Spark', 68)->and($scored)->toHaveKey('Query Optimization', 60)
        ->and($scored)->not->toHaveKey('SQL')
        ->and($card['not_covered'])->toContain('SQL');
    // The grader was told the facts too.
    expect($this->fake->calls[0]->user)->toContain('NOT talked about')->and($this->fake->calls[0]->user)->toContain('SQL');
});

/* ---- Ending is instant ---- */

it('closes the interview at once and builds the scorecard in the background', function (): void {
    Queue::fake();
    $mock = pipelineInterview($this->tenant);
    Sanctum::actingAs($mock->student);

    postJson("/api/v1/me/mocks/{$mock->id}/finish", ['background' => true])
        ->assertOk()
        ->assertJsonPath('data.ending', true)
        ->assertJsonPath('data.status', 'in_progress')
        ->assertJsonPath('data.scorecard', null);

    Queue::assertPushed(FinishMockInterviewJob::class, fn ($job) => $job->mockId === $mock->id);
    expect($this->fake->calls)->toBeEmpty(); // nothing slow happened in the request

    // Pressing it twice does not queue it twice.
    postJson("/api/v1/me/mocks/{$mock->id}/finish", ['background' => true])->assertOk();
    Queue::assertPushed(FinishMockInterviewJob::class, 1);

    // No more answers once it has ended.
    postJson("/api/v1/me/mocks/{$mock->id}/answer", ['answer' => 'one more thing'])->assertUnprocessable();
});

it('scores an ended interview when the background job runs', function (): void {
    Queue::fake();
    $mock = pipelineInterview($this->tenant);
    Sanctum::actingAs($mock->student);
    postJson("/api/v1/me/mocks/{$mock->id}/finish", ['background' => true])->assertOk();

    $this->fake->replies = [cardClaimingEverything()];
    (new FinishMockInterviewJob($mock->id))->handle(app(FinishMockInterview::class));

    expect($mock->fresh()->status)->toBe(MockInterview::STATUS_COMPLETED)
        ->and($mock->fresh()->overall_score)->toBe(61);
});

it('refuses to end before the minimum number of answers', function (): void {
    $mock = pipelineInterview($this->tenant);
    $mock->turns()->where('role', 'candidate')->delete();
    Sanctum::actingAs($mock->student);

    postJson("/api/v1/me/mocks/{$mock->id}/finish", ['background' => true])->assertUnprocessable();
});

it('queues the scorecard again when an ended interview was never scored', function (): void {
    Queue::fake();
    $mock = pipelineInterview($this->tenant);
    $mock->update(['ended_at' => now()->subMinutes(12)]);
    $fresh = pipelineInterview($this->tenant);
    $fresh->update(['ended_at' => now()->subMinutes(2)]);

    Artisan::call('interviews:remind');

    Queue::assertPushed(FinishMockInterviewJob::class, 1);
    Queue::assertPushed(FinishMockInterviewJob::class, fn ($job) => $job->mockId === $mock->id);
});

/* ---- Recording in slices, so ending never waits on an upload ---- */

it('joins the recording parts uploaded during the interview', function (): void {
    $mock = pipelineInterview($this->tenant);
    Sanctum::actingAs($mock->student);

    foreach (['AAAA', 'BBBB', 'CC'] as $i => $bytes) {
        $this->post("/api/v1/me/mocks/{$mock->id}/recording-part", [
            'seq' => $i, 'part' => UploadedFile::fake()->createWithContent("p{$i}.webm", $bytes),
        ], ['Accept' => 'application/json'])->assertOk();
    }
    postJson("/api/v1/me/mocks/{$mock->id}/recording-complete", ['parts' => 3])->assertOk();

    $path = "mock-recordings/{$mock->tenant_id}/{$mock->id}.webm";
    expect($mock->fresh()->recording_url)->toBe($path)
        ->and(Storage::disk('s3')->get($path))->toBe('AAAABBBBCC')
        ->and(Storage::disk('s3')->exists(AssembleMockRecording::partsDir($mock).'/0.part'))->toBeFalse();
});

it('leaves the recording alone when a part never arrived', function (): void {
    $mock = pipelineInterview($this->tenant);
    Sanctum::actingAs($mock->student);
    $this->post("/api/v1/me/mocks/{$mock->id}/recording-part", [
        'seq' => 0, 'part' => UploadedFile::fake()->createWithContent('p0.webm', 'AAAA'),
    ], ['Accept' => 'application/json'])->assertOk();

    postJson("/api/v1/me/mocks/{$mock->id}/recording-complete", ['parts' => 2])->assertOk(); // part 1 missing

    expect($mock->fresh()->recording_url)->toBeNull();
});

it('keeps recording parts to the interview owner', function (): void {
    $mock = pipelineInterview($this->tenant);
    Sanctum::actingAs(User::factory()->for($this->tenant)->create(['user_type' => 'student']));

    $this->post("/api/v1/me/mocks/{$mock->id}/recording-part", [
        'seq' => 0, 'part' => UploadedFile::fake()->createWithContent('p0.webm', 'AAAA'),
    ], ['Accept' => 'application/json'])->assertNotFound();
});

/* ---- The recording link in the client's report ---- */

it('opens the recording from the signed link without a login', function (): void {
    $mock = pipelineInterview($this->tenant);
    $mock->update(['recording_url' => "mock-recordings/{$mock->tenant_id}/{$mock->id}.webm"]);
    Storage::disk('s3')->put($mock->recording_url, 'video');

    get(RecordingLink::for($mock))->assertOk()
        ->assertSee("https://files.test/mock-recordings/{$mock->tenant_id}/{$mock->id}.webm", false)
        ->assertSee('Data Engineer');
});

it('says the recording is still processing, rather than failing, until it is saved', function (): void {
    $mock = pipelineInterview($this->tenant);

    get(RecordingLink::for($mock))->assertOk()->assertSee('still being processed');
});

it('refuses a recording link that was edited or has expired', function (): void {
    $mock = pipelineInterview($this->tenant);
    $url = RecordingLink::for($mock);

    $other = pipelineInterview($this->tenant);
    get(str_replace("/{$mock->id}?", "/{$other->id}?", $url))->assertForbidden();
    get("/api/v1/public/interview-recordings/{$mock->id}")->assertForbidden();

    $this->travel(RecordingLink::DAYS + 1)->days();
    get($url)->assertForbidden();
});
