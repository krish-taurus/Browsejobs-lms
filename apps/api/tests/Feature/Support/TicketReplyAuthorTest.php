<?php

declare(strict_types=1);

use App\Models\Tenant;
use App\Models\Ticket;
use App\Models\TicketMessage;
use App\Models\User;

/**
 * The student reads the name on a support reply. It used to be whichever staff
 * row happened to be first — a seeded demo counsellor — so a reply typed by a
 * real colleague arrived signed by someone who does not work here.
 */
beforeEach(function () {
    $this->tenant = Tenant::factory()->create();

    // The demo seed that used to win the fallback.
    $this->demo = User::factory()->for($this->tenant)->create([
        'user_type' => 'staff', 'name' => 'Aisha Rahman', 'email' => 'aisha.counselor@browsejobs.test',
    ]);

    $this->student = User::factory()->for($this->tenant)->create(['user_type' => 'student']);

    $this->ticket = withinTenant($this->tenant, fn () => Ticket::query()->create([
        'tenant_id' => $this->tenant->id,
        'student_id' => $this->student->id,
        'reference' => 'BJ-TEST01',
        'subject' => 'cant pay installment',
        'category' => 'payments',
        'priority' => 'normal',
        'status' => 'open',
    ]));
});

it('replies under the staff member who actually typed it', function () {
    $priyanka = User::factory()->for($this->tenant)->create([
        'user_type' => 'staff', 'name' => 'Priyanka Patnaik', 'email' => 'priyanka.patnaik@browsejobs.in',
    ]);

    $this->artisan('ticket:reply', [
        'ticket' => (string) $this->ticket->id,
        '--body' => 'Maximum one month extension can be done.',
        '--as' => 'priyanka.patnaik@browsejobs.in',
    ])->assertExitCode(0);

    expect(TicketMessage::withoutGlobalScopes()->latest('id')->value('author_id'))->toBe($priyanka->id);
});

it('creates the staff identity when the replier has no LMS account', function () {
    $this->artisan('ticket:reply', [
        'ticket' => (string) $this->ticket->id,
        '--body' => 'We can extend by a month.',
        '--as' => 'priyanka.patnaik@browsejobs.in',
        '--as-name' => 'Priyanka Patnaik',
    ])->assertExitCode(0);

    $author = User::withoutGlobalScopes()->find(TicketMessage::withoutGlobalScopes()->latest('id')->value('author_id'));

    expect($author->name)->toBe('Priyanka Patnaik')
        ->and($author->email)->toBe('priyanka.patnaik@browsejobs.in')
        // Never the demo seed.
        ->and($author->id)->not->toBe($this->demo->id);
});

it('derives a sensible name when the CRM sends only an email', function () {
    $this->artisan('ticket:reply', [
        'ticket' => (string) $this->ticket->id,
        '--body' => 'Noted.',
        '--as' => 'somali.bisoi@browsejobs.in',
    ])->assertExitCode(0);

    $author = User::withoutGlobalScopes()->find(TicketMessage::withoutGlobalScopes()->latest('id')->value('author_id'));

    expect($author->name)->toBe('Somali Bisoi');
});

it('avoids demo accounts when no identity is supplied at all', function () {
    $real = User::factory()->for($this->tenant)->create([
        'user_type' => 'staff', 'name' => 'Real Colleague', 'email' => 'real@browsejobs.in',
    ]);

    $this->artisan('ticket:reply', [
        'ticket' => (string) $this->ticket->id,
        '--body' => 'Automated follow-up.',
    ])->assertExitCode(0);

    expect(TicketMessage::withoutGlobalScopes()->latest('id')->value('author_id'))->toBe($real->id);
});
