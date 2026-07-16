<?php

declare(strict_types=1);

use App\Actions\Messaging\RecordInboundMessage;
use App\Enums\TicketStatus;
use App\Models\Tenant;
use App\Models\Ticket;
use App\Models\TicketMessage;
use App\Models\User;

it('threads an inbound WhatsApp reply onto the student open ticket', function () {
    $tenant = Tenant::factory()->create();
    $student = User::factory()->for($tenant)->create(['user_type' => 'student', 'phone' => '+91 90000 88888']);
    $ticket = Ticket::factory()->for($tenant)->create(['student_id' => $student->id, 'status' => TicketStatus::Open->value]);

    withinTenant($tenant, fn () => app(RecordInboundMessage::class)->handle($tenant->id, '+919000088888', 'Any update on this?'));

    $replies = TicketMessage::withoutGlobalScopes()
        ->where('ticket_id', $ticket->id)
        ->where('channel', 'whatsapp')
        ->get();

    expect($replies)->toHaveCount(1)
        ->and($replies->first()->body)->toBe('Any update on this?');
});

it('ignores an inbound message from a phone with no open ticket', function () {
    $tenant = Tenant::factory()->create();
    User::factory()->for($tenant)->create(['user_type' => 'student', 'phone' => '+91 90000 77777']);

    withinTenant($tenant, fn () => app(RecordInboundMessage::class)->handle($tenant->id, '+919000077777', 'Hello?'));

    expect(TicketMessage::withoutGlobalScopes()->count())->toBe(0);
});
