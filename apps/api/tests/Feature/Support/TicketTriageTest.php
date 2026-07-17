<?php

declare(strict_types=1);

use App\Actions\Support\TriageTicket;
use App\Enums\TicketPriority;
use App\Enums\TicketSentiment;
use App\Enums\TicketStatus;
use App\Models\AiEvent;
use App\Models\AuditLog;
use App\Models\Tenant;
use App\Models\Ticket;
use App\Models\TicketMessage;
use App\Models\User;
use App\Services\AI\AiClient;
use App\Services\AI\AiMessage;
use App\Services\AI\AiResult;
use App\Services\AI\FakeAiClient;

beforeEach(function () {
    $this->tenant = Tenant::factory()->create();
    $this->student = User::factory()->for($this->tenant)->create(['user_type' => 'student']);
    $this->staff = User::factory()->for($this->tenant)->create(['user_type' => 'staff']);

    $this->fake = new FakeAiClient;
    $this->fake->reply = '{"category":"payments","urgency":"normal","sentiment":"calm","duplicate_of":null}';
    app()->instance(AiClient::class, $this->fake);

    $this->ticket = function (array $attrs = []): Ticket {
        $ticket = Ticket::factory()->for($this->tenant)->create(array_merge([
            'student_id' => $this->student->id,
            'assignee_id' => $this->staff->id,
            'category' => 'technical',
            'priority' => TicketPriority::Normal->value,
            'status' => TicketStatus::Open->value,
            'subject' => 'My payment failed',
        ], $attrs));

        TicketMessage::factory()->for($this->tenant)->create([
            'ticket_id' => $ticket->id, 'author_type' => 'student',
            'body' => 'My EMI payment failed and my account is locked.', 'is_internal' => false,
        ]);

        return $ticket;
    };
});

it('stores the four triage signals and bills staff, not the student', function () {
    $ticket = ($this->ticket)();

    app(TriageTicket::class)->handle($ticket);

    $ticket->refresh();
    expect($ticket->ai_category->value)->toBe('payments')
        ->and($ticket->ai_urgency)->toBe(TicketPriority::Normal)
        ->and($ticket->ai_sentiment)->toBe(TicketSentiment::Calm)
        ->and($ticket->ai_triaged_at)->not->toBeNull()
        ->and($ticket->ai_event_id)->not->toBeNull();

    // The suggestion never overwrites the student's own category.
    expect($ticket->category->value)->toBe('technical');

    $events = AiEvent::withoutGlobalScopes()->where('purpose', 'support_triage')->get();
    expect($events)->toHaveCount(1)
        ->and($events->first()->user_id)->toBe($this->staff->id);
});

it('raises the priority of an angry ticket and audits it', function () {
    $this->fake->reply = '{"category":"payments","urgency":"normal","sentiment":"angry","duplicate_of":null}';
    $ticket = ($this->ticket)();

    app(TriageTicket::class)->handle($ticket);

    // PRD §6.13: "an angry payment dispute jumps the queue" — angry floors at high even
    // when the request itself reads routine.
    $ticket->refresh();
    expect($ticket->priority)->toBe(TicketPriority::High)
        ->and($ticket->ai_priority_raised)->toBeTrue();

    $audit = AuditLog::withoutGlobalScopes()->where('action', 'ticket.priority_raised_by_ai')->first();
    expect($audit)->not->toBeNull()
        ->and($audit->metadata['from'])->toBe('normal')
        ->and($audit->metadata['to'])->toBe('high');
});

it('never lowers a priority', function () {
    $this->fake->reply = '{"category":"payments","urgency":"low","sentiment":"calm","duplicate_of":null}';
    $ticket = ($this->ticket)(['priority' => TicketPriority::Urgent->value]);

    app(TriageTicket::class)->handle($ticket);

    $ticket->refresh();
    expect($ticket->priority)->toBe(TicketPriority::Urgent)
        ->and($ticket->ai_priority_raised)->toBeFalse()
        ->and($ticket->ai_urgency)->toBe(TicketPriority::Low); // recorded, not applied
});

it('does not re-prioritise a ticket a human is already working', function () {
    $this->fake->reply = '{"category":"payments","urgency":"urgent","sentiment":"angry","duplicate_of":null}';
    $ticket = ($this->ticket)();
    TicketMessage::factory()->for($this->tenant)->create([
        'ticket_id' => $ticket->id, 'author_type' => 'staff', 'body' => 'Looking into this now.', 'is_internal' => false,
    ]);

    app(TriageTicket::class)->handle($ticket);

    $ticket->refresh();
    expect($ticket->priority)->toBe(TicketPriority::Normal)
        ->and($ticket->ai_priority_raised)->toBeFalse()
        // The signals are still recorded for the staff member to see.
        ->and($ticket->ai_sentiment)->toBe(TicketSentiment::Angry);
});

it('is idempotent — a retry never re-raises', function () {
    $this->fake->reply = '{"category":"payments","urgency":"urgent","sentiment":"angry","duplicate_of":null}';
    $ticket = ($this->ticket)();

    app(TriageTicket::class)->handle($ticket);
    $ticket->refresh();

    // A human puts it back, then the job retries.
    $ticket->forceFill(['priority' => TicketPriority::Low->value, 'ai_priority_raised' => false])->save();
    app(TriageTicket::class)->handle($ticket->fresh());

    expect($ticket->fresh()->priority)->toBe(TicketPriority::Low)
        ->and($this->fake->calls)->toHaveCount(1); // and never paid for a second call
});

it('links a duplicate only when the id is one it was shown', function () {
    $existing = ($this->ticket)(['subject' => 'EMI payment failed']);
    $this->fake->reply = '{"category":"payments","urgency":"normal","sentiment":"neutral","duplicate_of":'.$existing->id.'}';

    $ticket = ($this->ticket)();
    app(TriageTicket::class)->handle($ticket);

    expect($ticket->fresh()->ai_duplicate_of_id)->toBe($existing->id);
});

it('ignores a duplicate id it was never shown', function () {
    // Another tenant's ticket id — a hallucinated or cross-tenant pointer must not stick.
    $other = Tenant::factory()->create();
    $theirs = Ticket::factory()->for($other)->create();
    $this->fake->reply = '{"category":"payments","urgency":"normal","sentiment":"neutral","duplicate_of":'.$theirs->id.'}';

    $ticket = ($this->ticket)();
    app(TriageTicket::class)->handle($ticket);

    expect($ticket->fresh()->ai_duplicate_of_id)->toBeNull();
});

it('leaves an ordinary ticket when the model returns nonsense', function () {
    $this->fake->reply = 'I am terribly sorry, I cannot help with that.';
    $ticket = ($this->ticket)();

    app(TriageTicket::class)->handle($ticket);

    $ticket->refresh();
    expect($ticket->ai_triaged_at)->toBeNull()
        ->and($ticket->ai_sentiment)->toBeNull()
        ->and($ticket->priority)->toBe(TicketPriority::Normal);
});

it('falls back to neutral sentiment on an unknown label', function () {
    $this->fake->reply = '{"category":"payments","urgency":"normal","sentiment":"apoplectic","duplicate_of":null}';
    $ticket = ($this->ticket)();

    app(TriageTicket::class)->handle($ticket);

    // Under-reacting is the fail-safe: a bogus label must not manufacture urgency.
    expect($ticket->fresh()->ai_sentiment)->toBe(TicketSentiment::Neutral)
        ->and($ticket->fresh()->ai_priority_raised)->toBeFalse();
});

it('leaves an ordinary ticket when the model is down', function () {
    app()->instance(AiClient::class, new class implements AiClient
    {
        public function complete(AiMessage $m): AiResult
        {
            throw new RuntimeException('upstream down');
        }
    });

    $ticket = ($this->ticket)();
    app(TriageTicket::class)->handle($ticket);

    expect($ticket->fresh()->ai_triaged_at)->toBeNull();
});

it('does not triage when switched off', function () {
    config()->set('support.ai.triage_enabled', false);
    $ticket = ($this->ticket)();

    app(TriageTicket::class)->handle($ticket);

    expect($ticket->fresh()->ai_triaged_at)->toBeNull()
        ->and($this->fake->calls)->toBeEmpty();
});

it('skips rather than billing a student when there is no staff to bill', function () {
    $ticket = ($this->ticket)(['assignee_id' => null]);

    app(TriageTicket::class)->handle($ticket);

    expect($ticket->fresh()->ai_triaged_at)->toBeNull()
        ->and($this->fake->calls)->toBeEmpty()
        ->and(AiEvent::withoutGlobalScopes()->where('user_id', $this->student->id)->count())->toBe(0);
});

it('only offers duplicate candidates from the same student', function () {
    $other = User::factory()->for($this->tenant)->create(['user_type' => 'student']);
    Ticket::factory()->for($this->tenant)->create([
        'student_id' => $other->id, 'subject' => 'Someone else EMI problem', 'status' => TicketStatus::Open->value,
    ]);

    $ticket = ($this->ticket)();
    app(TriageTicket::class)->handle($ticket);

    expect($this->fake->calls[0]->user)->not->toContain('Someone else EMI problem');
});
