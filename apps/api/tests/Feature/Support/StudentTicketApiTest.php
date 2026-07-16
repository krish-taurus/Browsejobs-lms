<?php

declare(strict_types=1);

use App\Models\Tenant;
use App\Models\Ticket;
use App\Models\TicketAttachment;
use App\Models\TicketMessage;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    Storage::fake('s3');
    $this->tenant = Tenant::factory()->create();
    $this->student = User::factory()->for($this->tenant)->create(['user_type' => 'student', 'phone' => '+91 90000 90001']);
});

it('raises a ticket with an attachment', function () {
    Sanctum::actingAs($this->student);

    $this->post('/api/v1/me/tickets', [
        'category' => 'technical',
        'subject' => 'Recordings will not load',
        'body' => 'The recordings page just spins forever on my phone.',
        'attachments' => [UploadedFile::fake()->image('screenshot.png')],
    ], ['Accept' => 'application/json'])->assertCreated()->assertJsonPath('data.status', 'open');

    $ticket = Ticket::withoutGlobalScopes()->first();
    expect($ticket->student_id)->toBe($this->student->id)
        ->and(TicketAttachment::withoutGlobalScopes()->where('ticket_id', $ticket->id)->count())->toBe(1)
        ->and(Storage::disk('s3')->exists(TicketAttachment::withoutGlobalScopes()->first()->path))->toBeTrue();
});

it('rejects an invalid attachment type', function () {
    Sanctum::actingAs($this->student);

    $this->post('/api/v1/me/tickets', [
        'category' => 'technical', 'subject' => 'X', 'body' => 'a valid body here',
        'attachments' => [UploadedFile::fake()->create('virus.exe', 10, 'application/x-msdownload')],
    ], ['Accept' => 'application/json'])->assertStatus(422);
});

it('lists only the student own tickets', function () {
    Ticket::factory()->for($this->tenant)->create(['student_id' => $this->student->id]);
    $other = User::factory()->for($this->tenant)->create(['user_type' => 'student']);
    Ticket::factory()->for($this->tenant)->create(['student_id' => $other->id]);

    Sanctum::actingAs($this->student);

    $this->getJson('/api/v1/me/tickets')->assertOk()->assertJsonCount(1, 'data');
});

it('hides internal notes on show', function () {
    $ticket = Ticket::factory()->for($this->tenant)->create(['student_id' => $this->student->id]);
    TicketMessage::factory()->for($this->tenant)->create(['ticket_id' => $ticket->id, 'body' => 'public reply', 'is_internal' => false]);
    TicketMessage::factory()->for($this->tenant)->create(['ticket_id' => $ticket->id, 'body' => 'secret note', 'is_internal' => true]);

    Sanctum::actingAs($this->student);

    $this->getJson("/api/v1/me/tickets/{$ticket->id}")
        ->assertOk()
        ->assertJsonFragment(['body' => 'public reply'])
        ->assertJsonMissing(['body' => 'secret note']);
});

it('404s another student ticket', function () {
    $other = User::factory()->for($this->tenant)->create(['user_type' => 'student']);
    $foreign = Ticket::factory()->for($this->tenant)->create(['student_id' => $other->id]);

    Sanctum::actingAs($this->student);

    $this->getJson("/api/v1/me/tickets/{$foreign->id}")->assertNotFound();
});

it('replies, reopens and rates a ticket', function () {
    $ticket = Ticket::factory()->for($this->tenant)->resolved()->create(['student_id' => $this->student->id]);

    Sanctum::actingAs($this->student);

    $this->postJson("/api/v1/me/tickets/{$ticket->id}/csat", ['rating' => 4])->assertOk();
    expect($ticket->fresh()->csat_rating)->toBe(4);

    $this->postJson("/api/v1/me/tickets/{$ticket->id}/reopen")->assertOk();
    expect($ticket->fresh()->status->value)->toBe('in_progress');

    $this->postJson("/api/v1/me/tickets/{$ticket->id}/reply", ['body' => 'Still not working.'])->assertOk();
    expect(TicketMessage::withoutGlobalScopes()->where('ticket_id', $ticket->id)->count())->toBe(1);
});
