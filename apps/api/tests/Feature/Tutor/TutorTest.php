<?php

declare(strict_types=1);

use App\Actions\Tutor\AskTutor;
use App\Actions\Tutor\IngestKnowledgeDocument;
use App\Actions\Tutor\RebuildTutorIndex;
use App\Enums\TicketCategory;
use App\Models\CodeSubmission;
use App\Models\CodingLab;
use App\Models\Course;
use App\Models\KnowledgeDocument;
use App\Models\Lesson;
use App\Models\Tenant;
use App\Models\Ticket;
use App\Models\TutorConversation;
use App\Models\TutorMessage;
use App\Models\User;
use App\Services\AI\AiClient;
use App\Services\AI\FakeAiClient;
use Database\Seeders\RolePermissionSeeder;
use Laravel\Sanctum\Sanctum;

use function Pest\Laravel\getJson;
use function Pest\Laravel\patchJson;
use function Pest\Laravel\postJson;

beforeEach(function () {
    $this->fake = new FakeAiClient;
    app()->instance(AiClient::class, $this->fake);
    $this->tenant = Tenant::factory()->create();
    $this->student = User::factory()->for($this->tenant)->create(['user_type' => 'student']);
});

function conversationFor(User $student): TutorConversation
{
    return withinTenant($student->tenant, fn () => TutorConversation::create([
        'tenant_id' => $student->tenant_id, 'student_id' => $student->id, 'title' => 'Test',
    ]));
}

/** Seed one active knowledge document with the given body and chunk it. */
function seedKnowledge(Tenant $tenant, string $body): void
{
    $doc = KnowledgeDocument::factory()->for($tenant)->create(['body' => $body, 'is_active' => true]);
    app(IngestKnowledgeDocument::class)->handle($doc);
}

/* ---- Ingest + reindex ---- */

it('chunks a document and replaces chunks on re-ingest', function () {
    $doc = KnowledgeDocument::factory()->for($this->tenant)->create([
        'body' => "First paragraph about loops.\n\nSecond paragraph about functions.",
    ]);

    $count = app(IngestKnowledgeDocument::class)->handle($doc);
    expect($count)->toBeGreaterThanOrEqual(1)
        ->and($doc->chunks()->count())->toBe($count);

    // Re-ingest replaces (does not duplicate).
    app(IngestKnowledgeDocument::class)->handle($doc);
    expect($doc->chunks()->count())->toBe($count);
});

it('rebuilds the index from course and lab content', function () {
    $course = Course::factory()->for($this->tenant)->create(['description' => 'A course on Python data structures and algorithms.']);
    $lesson = Lesson::factory()->for($this->tenant)->codingLab()->create();
    CodingLab::factory()->for($this->tenant)->create(['lesson_id' => $lesson->id, 'instructions' => 'Implement a binary search over a sorted list.']);

    $summary = app(RebuildTutorIndex::class)->handle($this->tenant);

    expect($summary['documents'])->toBeGreaterThanOrEqual(2)
        ->and($summary['chunks'])->toBeGreaterThanOrEqual(2);

    withinTenant($this->tenant, function () use ($course) {
        expect(KnowledgeDocument::where('source_type', 'course')->where('source_id', $course->id)->exists())->toBeTrue()
            ->and(KnowledgeDocument::where('source_type', 'coding_lab')->exists())->toBeTrue();
    });
});

/* ---- AskTutor pipeline ---- */

it('answers with citations and strips the confidence line', function () {
    seedKnowledge($this->tenant, 'Recursion is a function calling itself until a base case.');
    $this->fake->reply = "Think about the base case [C1]. That stops the recursion.\n\nCONFIDENCE: high";

    $conversation = conversationFor($this->student);
    $message = app(AskTutor::class)->handle($this->student, 'How does recursion terminate?', $conversation);

    expect($message->role)->toBe('tutor')
        ->and($message->confidence)->toBe('high')
        ->and($message->escalated)->toBeFalse()
        ->and($message->body)->not->toContain('CONFIDENCE')
        ->and($message->cited_chunk_ids)->not->toBeEmpty();
});

it('never leaks hidden test outputs into the lab hint prompt', function () {
    $lesson = Lesson::factory()->for($this->tenant)->codingLab()->create();
    $lab = CodingLab::factory()->for($this->tenant)->create([
        'lesson_id' => $lesson->id,
        'instructions' => 'Read two integers and print their sum.',
        'test_cases' => [['stdin' => "1\n2\n", 'expected_stdout' => 'SECRETEXPECTED42']],
    ]);
    withinTenant($this->tenant, fn () => CodeSubmission::create([
        'tenant_id' => $this->tenant->id, 'user_id' => $this->student->id, 'lesson_id' => $lesson->id,
        'language' => 'python', 'source' => 'print(1)', 'stdout' => 'MYRUNOUTPUT', 'stderr' => '',
        'status' => 'ok', 'passed_tests' => 0, 'total_tests' => 1, 'kind' => 'run',
    ]));
    $this->fake->reply = "Check your input parsing [C1].\n\nCONFIDENCE: high";

    $conversation = conversationFor($this->student);
    app(AskTutor::class)->handle($this->student, 'Why does my sum fail?', $conversation, $lab);

    $prompt = $this->fake->calls[0]->user;
    expect($prompt)->toContain('Read two integers')      // lab instructions are provided
        ->and($prompt)->toContain('MYRUNOUTPUT')         // the student's own run is provided
        ->and($prompt)->not->toContain('SECRETEXPECTED42'); // hidden expected output is NOT
});

it('escalates a low-confidence answer to an academic trainer ticket', function () {
    $this->seed(RolePermissionSeeder::class);
    seedKnowledge($this->tenant, 'Unrelated content about databases.');
    $this->fake->reply = "I could not find this in your course.\n\nCONFIDENCE: low";

    $conversation = conversationFor($this->student);
    $message = app(AskTutor::class)->handle($this->student, 'Explain quantum entanglement please', $conversation);

    expect($message->escalated)->toBeTrue()->and($message->ticket_id)->not->toBeNull();

    withinTenant($this->tenant, function () use ($message) {
        $ticket = Ticket::find($message->ticket_id);
        expect($ticket->category)->toBe(TicketCategory::Academic)
            ->and($ticket->student_id)->toBe($this->student->id);
    });
});

it('escalates a repeated question even at high confidence', function () {
    config(['ai.tutor.repeat_threshold' => 2]);
    seedKnowledge($this->tenant, 'Loops repeat a block of code.');
    $this->fake->reply = "A for loop iterates a range [C1].\n\nCONFIDENCE: high";

    $conversation = conversationFor($this->student);
    $first = app(AskTutor::class)->handle($this->student, 'How do for loops work?', $conversation);
    $second = app(AskTutor::class)->handle($this->student, 'How do for loops work?', $conversation);

    expect($first->escalated)->toBeFalse()
        ->and($second->escalated)->toBeTrue()
        ->and(Ticket::withoutGlobalScopes()->where('category', 'academic')->count())->toBe(1);
});

/* ---- HTTP: me/tutor ---- */

it('serves a cited answer over http and persists the thread', function () {
    seedKnowledge($this->tenant, 'A dictionary maps keys to values.');
    $this->fake->reply = "Use a dict literal [C1].\n\nCONFIDENCE: high";
    Sanctum::actingAs($this->student);

    postJson('/api/v1/me/tutor', ['question' => 'How do I make a dictionary?'])
        ->assertOk()
        ->assertJsonPath('data.messages.1.role', 'tutor')
        ->assertJsonPath('data.messages.1.confidence', 'high');

    expect(TutorMessage::withoutGlobalScopes()->where('role', 'tutor')->count())->toBe(1);
});

it('returns a 429 budget envelope when the daily token budget is spent', function () {
    config(['ai.daily_token_budget' => 50]); // fake spends 60 tokens per call
    seedKnowledge($this->tenant, 'Some content.');
    $this->fake->reply = "Answer.\n\nCONFIDENCE: high";
    Sanctum::actingAs($this->student);

    postJson('/api/v1/me/tutor', ['question' => 'A first question here?'])->assertOk();

    postJson('/api/v1/me/tutor', ['question' => 'A second question here?'])
        ->assertStatus(429)
        ->assertJsonPath('error.code', 'ai_budget_exceeded');
});

it('rejects the tutor for guests', function () {
    getJson('/api/v1/me/tutor')->assertUnauthorized();
});

it('does not expose another tenant\'s conversation', function () {
    $other = Tenant::factory()->create();
    $otherStudent = User::factory()->for($other)->create(['user_type' => 'student']);
    $conversation = conversationFor($otherStudent);

    Sanctum::actingAs($this->student);
    getJson("/api/v1/me/tutor/{$conversation->id}")->assertNotFound();
});

/* ---- HTTP: admin/knowledge ---- */

it('lets a curriculum manager author a knowledge document', function () {
    $this->seed(RolePermissionSeeder::class);
    $admin = User::factory()->for($this->tenant)->create(['user_type' => 'staff']);
    $admin->assignRole('admin');
    Sanctum::actingAs($admin);

    postJson('/api/v1/admin/knowledge', ['title' => 'Study tips', 'body' => "Practice daily.\n\nReview weekly."])
        ->assertCreated()
        ->assertJsonPath('data.source_type', 'manual');

    expect(KnowledgeDocument::withoutGlobalScopes()->where('title', 'Study tips')->exists())->toBeTrue();
});

it('denies knowledge authoring without manage-curriculum', function () {
    $this->seed(RolePermissionSeeder::class);
    $counselor = counselorIn($this->tenant);
    Sanctum::actingAs($counselor);

    getJson('/api/v1/admin/knowledge')->assertForbidden();
});

it('does not update another tenant\'s knowledge document', function () {
    $this->seed(RolePermissionSeeder::class);
    $other = Tenant::factory()->create();
    $foreignDoc = KnowledgeDocument::factory()->for($other)->create(['source_type' => 'manual']);

    $admin = User::factory()->for($this->tenant)->create(['user_type' => 'staff']);
    $admin->assignRole('admin');
    Sanctum::actingAs($admin);

    // Route-model binding is tenant-scoped, so the foreign document never resolves.
    patchJson("/api/v1/admin/knowledge/{$foreignDoc->id}", ['title' => 'Hijack', 'body' => 'x y z'])
        ->assertNotFound();
});

/* ---- Command ---- */

it('runs the tutor:reindex command', function () {
    Course::factory()->for($this->tenant)->create(['description' => 'Content to index.']);

    $this->artisan('tutor:reindex')->assertSuccessful();

    expect(KnowledgeDocument::withoutGlobalScopes()->count())->toBeGreaterThanOrEqual(1);
});
