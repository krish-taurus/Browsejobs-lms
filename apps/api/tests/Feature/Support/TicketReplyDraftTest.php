<?php

declare(strict_types=1);

use App\Models\AiEvent;
use App\Models\KnowledgeChunk;
use App\Models\KnowledgeDocument;
use App\Models\Tenant;
use App\Models\Ticket;
use App\Models\TicketMessage;
use App\Models\User;
use App\Services\AI\AiClient;
use App\Services\AI\AiMessage;
use App\Services\AI\AiResult;
use App\Services\AI\FakeAiClient;
use Database\Seeders\RolePermissionSeeder;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
    $this->tenant = Tenant::factory()->create();
    $this->student = User::factory()->for($this->tenant)->create(['user_type' => 'student', 'name' => 'Asha']);
    $this->staff = User::factory()->for($this->tenant)->create(['user_type' => 'staff']);
    $this->staff->assignRole('admin');

    $this->fake = new FakeAiClient;
    $this->fake->reply = 'Your EMI of ₹10,000 is due on 5 August. You can pay it from the fees page.';
    app()->instance(AiClient::class, $this->fake);

    $this->ticket = Ticket::factory()->for($this->tenant)->create([
        'student_id' => $this->student->id,
        'assignee_id' => $this->staff->id,
        'category' => 'payments',
        'subject' => 'When is my EMI due',
    ]);

    TicketMessage::factory()->for($this->tenant)->create([
        'ticket_id' => $this->ticket->id, 'author_type' => 'student',
        'body' => 'When is my next instalment due?', 'is_internal' => false,
    ]);

    withinTenant($this->tenant, function () {
        $doc = KnowledgeDocument::factory()->for($this->tenant)->create([
            'source_type' => 'support', 'category' => 'payments', 'is_active' => true,
            'title' => 'Registration fee and EMI schedule',
        ]);
        KnowledgeChunk::factory()->for($this->tenant)->create([
            'document_id' => $doc->id, 'ordinal' => 0,
            'content' => 'Registration is ₹30,000, payable as 3 monthly instalments of ₹10,000.',
            'term_count' => 10,
        ]);
    });
});

it('drafts a reply for staff without sending anything', function () {
    Sanctum::actingAs($this->staff);

    $this->postJson("/api/v1/admin/tickets/{$this->ticket->id}/draft-reply")
        ->assertOk()
        ->assertJsonPath('data', 'Your EMI of ₹10,000 is due on 5 August. You can pay it from the fees page.');

    // A draft is not a message: nothing was posted, and the SLA clock is untouched.
    expect(TicketMessage::withoutGlobalScopes()->where('author_type', 'staff')->count())->toBe(0)
        ->and($this->ticket->fresh()->first_response_at)->toBeNull();
});

it('bills the staff member who asked, not the student', function () {
    Sanctum::actingAs($this->staff);

    $this->postJson("/api/v1/admin/tickets/{$this->ticket->id}/draft-reply")->assertOk();

    $event = AiEvent::withoutGlobalScopes()->where('purpose', 'support_reply')->firstOrFail();
    expect($event->user_id)->toBe($this->staff->id);
});

it('grounds the draft in the support corpus and the student own account', function () {
    Sanctum::actingAs($this->staff);

    $this->postJson("/api/v1/admin/tickets/{$this->ticket->id}/draft-reply")->assertOk();

    $prompt = $this->fake->calls[0]->user;
    expect($prompt)->toContain('Registration fee and EMI schedule')
        ->and($prompt)->toContain('Name: Asha')
        ->and($prompt)->toContain('When is my next instalment due?');
});

it('tells the model plainly when no policy covers the question', function () {
    $bare = Ticket::factory()->for($this->tenant)->create([
        'student_id' => $this->student->id, 'category' => 'mentorship', 'subject' => 'Mentor question',
    ]);
    TicketMessage::factory()->for($this->tenant)->create([
        'ticket_id' => $bare->id, 'author_type' => 'student', 'body' => 'Can I switch mentors?', 'is_internal' => false,
    ]);

    Sanctum::actingAs($this->staff);
    $this->postJson("/api/v1/admin/tickets/{$bare->id}/draft-reply")->assertOk();

    expect($this->fake->calls[0]->user)->toContain('Do not invent one.');
});

it('returns no draft when the model is down', function () {
    app()->instance(AiClient::class, new class implements AiClient
    {
        public function complete(AiMessage $m): AiResult
        {
            throw new RuntimeException('upstream down');
        }
    });

    Sanctum::actingAs($this->staff);

    // The workspace still has its canned responses and an empty box — exactly P2.7.
    $this->postJson("/api/v1/admin/tickets/{$this->ticket->id}/draft-reply")
        ->assertOk()->assertJsonPath('data', null);
});

it('returns no draft when reply drafts are switched off', function () {
    config()->set('support.ai.reply_drafts_enabled', false);
    Sanctum::actingAs($this->staff);

    $this->postJson("/api/v1/admin/tickets/{$this->ticket->id}/draft-reply")
        ->assertOk()->assertJsonPath('data', null);

    expect($this->fake->calls)->toBeEmpty();
});

it('denies a user without handle-tickets', function () {
    $other = User::factory()->for($this->tenant)->create(['user_type' => 'staff']);
    Sanctum::actingAs($other);

    $this->postJson("/api/v1/admin/tickets/{$this->ticket->id}/draft-reply")->assertForbidden();
});

it('404s a foreign tenant ticket', function () {
    $other = Tenant::factory()->create();
    $theirs = Ticket::factory()->for($other)->create();

    Sanctum::actingAs($this->staff);

    $this->postJson("/api/v1/admin/tickets/{$theirs->id}/draft-reply")->assertNotFound();
});
