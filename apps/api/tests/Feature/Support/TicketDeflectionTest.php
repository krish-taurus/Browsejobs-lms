<?php

declare(strict_types=1);

use App\Enums\DeflectionOutcome;
use App\Models\AiEvent;
use App\Models\KnowledgeChunk;
use App\Models\KnowledgeDocument;
use App\Models\Tenant;
use App\Models\Ticket;
use App\Models\TicketDeflection;
use App\Models\User;
use App\Services\AI\AiClient;
use App\Services\AI\AiMessage;
use App\Services\AI\AiResult;
use App\Services\AI\FakeAiClient;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->tenant = Tenant::factory()->create();
    $this->student = User::factory()->for($this->tenant)->create(['user_type' => 'student']);

    $this->fake = new FakeAiClient;
    $this->fake->reply = "Registration is ₹30,000 and you pay it after the free masterclass [C1].\n\nCONFIDENCE: high";
    app()->instance(AiClient::class, $this->fake);

    // A payments policy document — the corpus deflection is allowed to answer from.
    $this->seedSupportDoc = function (string $category = 'payments', string $content = 'Registration is ₹30,000 payable after the free masterclass. EMI is 3 instalments of ₹10,000.'): KnowledgeChunk {
        return withinTenant($this->tenant, function () use ($category, $content): KnowledgeChunk {
            $doc = KnowledgeDocument::factory()->for($this->tenant)->create([
                'source_type' => 'support',
                'category' => $category,
                'is_active' => true,
                'title' => 'Registration fee and EMI schedule',
            ]);

            return KnowledgeChunk::factory()->for($this->tenant)->create([
                'document_id' => $doc->id, 'ordinal' => 0,
                'content' => $content, 'term_count' => 12,
            ]);
        });
    };
});

it('offers a grounded answer with citations before the ticket exists', function () {
    ($this->seedSupportDoc)();
    Sanctum::actingAs($this->student);

    $response = $this->postJson('/api/v1/me/tickets/deflect', [
        'category' => 'payments',
        'body' => 'How much is the registration fee?',
    ])->assertOk();

    $response->assertJsonPath('data.confidence', 'high')
        ->assertJsonPath('data.outcome', 'offered')
        ->assertJsonPath('data.citations.0.label', 'Registration fee and EMI schedule');

    // The CONFIDENCE line is stripped off the student-facing answer.
    expect($response->json('data.answer'))->not->toContain('CONFIDENCE');

    // Offered, and billed to the student's own budget — no ticket created.
    $deflection = TicketDeflection::withoutGlobalScopes()->firstOrFail();
    expect($deflection->outcome)->toBe(DeflectionOutcome::Offered)
        ->and($deflection->ai_event_id)->not->toBeNull()
        ->and(Ticket::withoutGlobalScopes()->count())->toBe(0)
        ->and(AiEvent::withoutGlobalScopes()->where('user_id', $this->student->id)->where('purpose', 'support_deflection')->count())->toBe(1);
});

it('passes the student own next instalment into the prompt rather than estimating it', function () {
    ($this->seedSupportDoc)();
    Sanctum::actingAs($this->student);

    $this->postJson('/api/v1/me/tickets/deflect', [
        'category' => 'payments',
        'body' => 'When is my next EMI due?',
    ])->assertOk();

    // The model is told the account facts verbatim; the prompt forbids it inventing them.
    expect($this->fake->calls[0]->user)->toContain('Next instalment: none due');
});

it('does not answer a payments question out of course material', function () {
    // Course content mentioning fees exists, but it is not the support corpus.
    withinTenant($this->tenant, function () {
        $doc = KnowledgeDocument::factory()->for($this->tenant)->create([
            'source_type' => 'course', 'category' => null, 'is_active' => true,
        ]);
        KnowledgeChunk::factory()->for($this->tenant)->create([
            'document_id' => $doc->id, 'ordinal' => 0,
            'content' => 'This course covers registration of variables and fee calculation exercises.',
            'term_count' => 10,
        ]);
    });

    Sanctum::actingAs($this->student);

    $this->postJson('/api/v1/me/tickets/deflect', [
        'category' => 'payments',
        'body' => 'How much is the registration fee?',
    ])->assertOk()->assertJsonPath('data', null);

    expect($this->fake->calls)->toBeEmpty(); // never even called the model
});

it('does not answer from another category policy', function () {
    ($this->seedSupportDoc)('training', 'You can freeze your enrolment and rejoin a later batch.');
    Sanctum::actingAs($this->student);

    $this->postJson('/api/v1/me/tickets/deflect', [
        'category' => 'payments',
        'body' => 'Can I freeze my enrolment?',
    ])->assertOk()->assertJsonPath('data', null);
});

it('offers nothing when the model is not confident', function () {
    ($this->seedSupportDoc)();
    $this->fake->reply = "It depends on your case.\n\nCONFIDENCE: low";
    Sanctum::actingAs($this->student);

    $this->postJson('/api/v1/me/tickets/deflect', [
        'category' => 'payments',
        'body' => 'How much is the registration fee?',
    ])->assertOk()->assertJsonPath('data', null);

    expect(TicketDeflection::withoutGlobalScopes()->count())->toBe(0);
});

it('treats a missing confidence signal as low (fail-safe)', function () {
    ($this->seedSupportDoc)();
    $this->fake->reply = 'Registration is ₹30,000.'; // no CONFIDENCE line at all
    Sanctum::actingAs($this->student);

    $this->postJson('/api/v1/me/tickets/deflect', [
        'category' => 'payments',
        'body' => 'How much is the registration fee?',
    ])->assertOk()->assertJsonPath('data', null);
});

it('lets the ticket through when the model is down', function () {
    ($this->seedSupportDoc)();
    app()->instance(AiClient::class, new class implements AiClient
    {
        public function complete(AiMessage $m): AiResult
        {
            throw new RuntimeException('upstream down');
        }
    });
    Sanctum::actingAs($this->student);

    // Deflection degrades to "no answer" — never an error the student has to get past.
    $this->postJson('/api/v1/me/tickets/deflect', [
        'category' => 'payments',
        'body' => 'How much is the registration fee?',
    ])->assertOk()->assertJsonPath('data', null);

    // And raising the ticket still works.
    $this->postJson('/api/v1/me/tickets', [
        'category' => 'payments', 'subject' => 'Fee question', 'body' => 'How much is the registration fee?',
    ])->assertCreated();
});

it('lets the ticket through when the student AI budget is exhausted', function () {
    ($this->seedSupportDoc)();
    config()->set('ai.daily_token_budget', 10);
    AiEvent::factory()->for($this->tenant)->create([
        'user_id' => $this->student->id, 'purpose' => 'tutor', 'total_tokens' => 50,
    ]);

    Sanctum::actingAs($this->student);

    $this->postJson('/api/v1/me/tickets/deflect', [
        'category' => 'payments',
        'body' => 'How much is the registration fee?',
    ])->assertOk()->assertJsonPath('data', null);

    $this->postJson('/api/v1/me/tickets', [
        'category' => 'payments', 'subject' => 'Fee question', 'body' => 'How much is the registration fee?',
    ])->assertCreated();
});

it('offers nothing when deflection is switched off', function () {
    ($this->seedSupportDoc)();
    config()->set('support.ai.enabled', false);
    Sanctum::actingAs($this->student);

    $this->postJson('/api/v1/me/tickets/deflect', [
        'category' => 'payments', 'body' => 'How much is the registration fee?',
    ])->assertOk()->assertJsonPath('data', null);

    expect($this->fake->calls)->toBeEmpty();
});

it('records an accepted answer as a deflected ticket', function () {
    $deflection = TicketDeflection::factory()->for($this->tenant)->create(['student_id' => $this->student->id]);
    Sanctum::actingAs($this->student);

    $this->postJson("/api/v1/me/tickets/deflect/{$deflection->id}/accept")->assertOk();

    expect($deflection->fresh()->outcome)->toBe(DeflectionOutcome::Accepted)
        ->and($deflection->fresh()->resolved_at)->not->toBeNull()
        ->and(Ticket::withoutGlobalScopes()->count())->toBe(0);
});

it('links a proceeded answer to the ticket it failed to prevent', function () {
    $deflection = TicketDeflection::factory()->for($this->tenant)->create(['student_id' => $this->student->id]);
    Sanctum::actingAs($this->student);

    $this->postJson('/api/v1/me/tickets', [
        'category' => 'payments',
        'subject' => 'Still confused about the fee',
        'body' => 'That did not answer my question about the fee.',
        'deflection_id' => $deflection->id,
    ])->assertCreated();

    $ticket = Ticket::withoutGlobalScopes()->firstOrFail();
    expect($deflection->fresh()->outcome)->toBe(DeflectionOutcome::Proceeded)
        ->and($deflection->fresh()->ticket_id)->toBe($ticket->id);
});

it('keeps the first outcome when resolution is repeated', function () {
    $deflection = TicketDeflection::factory()->for($this->tenant)->accepted()->create(['student_id' => $this->student->id]);
    Sanctum::actingAs($this->student);

    $this->postJson("/api/v1/me/tickets/deflect/{$deflection->id}/accept")->assertOk();

    expect($deflection->fresh()->outcome)->toBe(DeflectionOutcome::Accepted);
});

it('raises the ticket anyway when the deflection id is unknown', function () {
    Sanctum::actingAs($this->student);

    // Bookkeeping must never block someone reaching a human.
    $this->postJson('/api/v1/me/tickets', [
        'category' => 'payments', 'subject' => 'Fee question',
        'body' => 'How much is the registration fee?', 'deflection_id' => 99999,
    ])->assertCreated();
});

it('requires authentication', function () {
    $this->postJson('/api/v1/me/tickets/deflect', ['category' => 'payments', 'body' => 'How much is it?'])
        ->assertUnauthorized();
});

it('validates the draft concern', function () {
    Sanctum::actingAs($this->student);

    $this->postJson('/api/v1/me/tickets/deflect', ['category' => 'nonsense', 'body' => 'hi'])
        ->assertStatus(422);
});

it('denies accepting another tenant deflection', function () {
    $other = Tenant::factory()->create();
    $otherStudent = User::factory()->for($other)->create(['user_type' => 'student']);
    $theirs = TicketDeflection::factory()->for($other)->create(['student_id' => $otherStudent->id]);

    Sanctum::actingAs($this->student);

    $this->postJson("/api/v1/me/tickets/deflect/{$theirs->id}/accept")->assertNotFound();

    expect($theirs->fresh()->outcome)->toBe(DeflectionOutcome::Offered);
});

it('never retrieves another tenant support corpus', function () {
    // Tenant B has the only payments policy; tenant A's student must not see it.
    $other = Tenant::factory()->create();
    withinTenant($other, function () use ($other) {
        $doc = KnowledgeDocument::factory()->for($other)->create([
            'source_type' => 'support', 'category' => 'payments', 'is_active' => true,
            'title' => 'Other tenant fee policy',
        ]);
        KnowledgeChunk::factory()->for($other)->create([
            'document_id' => $doc->id, 'ordinal' => 0,
            'content' => 'Registration at the other tenant is ₹99,000.', 'term_count' => 8,
        ]);
    });

    Sanctum::actingAs($this->student);

    $this->postJson('/api/v1/me/tickets/deflect', [
        'category' => 'payments', 'body' => 'How much is the registration fee?',
    ])->assertOk()->assertJsonPath('data', null);

    expect($this->fake->calls)->toBeEmpty();
});
