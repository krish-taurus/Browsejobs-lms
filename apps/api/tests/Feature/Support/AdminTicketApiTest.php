<?php

declare(strict_types=1);

use App\Models\CannedResponse;
use App\Models\Tenant;
use App\Models\Ticket;
use App\Models\TicketMessage;
use App\Models\TicketRoute;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
    $this->tenant = Tenant::factory()->create();
    $this->admin = User::factory()->for($this->tenant)->create(['user_type' => 'staff']);
    $this->admin->assignRole('admin');
    $this->student = User::factory()->for($this->tenant)->create(['user_type' => 'student']);
});

it('lists the ticket queue scoped to the tenant and filtered by status', function () {
    Ticket::factory()->for($this->tenant)->create(['student_id' => $this->student->id, 'status' => 'open']);
    Ticket::factory()->for($this->tenant)->create(['student_id' => $this->student->id, 'status' => 'resolved']);
    Ticket::factory()->for(Tenant::factory()->create())->create();

    Sanctum::actingAs($this->admin);

    $this->getJson('/api/v1/admin/tickets')->assertOk()->assertJsonCount(2, 'data');
    $this->getJson('/api/v1/admin/tickets?status=open')->assertOk()->assertJsonCount(1, 'data');
});

it('shows a ticket with the student-context sidebar', function () {
    $ticket = Ticket::factory()->for($this->tenant)->create(['student_id' => $this->student->id]);

    Sanctum::actingAs($this->admin);

    $this->getJson("/api/v1/admin/tickets/{$ticket->id}")
        ->assertOk()
        ->assertJsonPath('data.reference', $ticket->reference)
        ->assertJsonPath('context.student.id', $this->student->id)
        ->assertJsonStructure(['context' => ['batch', 'fee', 'attendance_pct', 'recent_tickets']]);
});

it('replies (internal), changes status, assigns and escalates', function () {
    $agent = User::factory()->for($this->tenant)->create(['user_type' => 'staff']);
    $agent->assignRole('support-agent');
    $ticket = Ticket::factory()->for($this->tenant)->create(['student_id' => $this->student->id]);

    Sanctum::actingAs($this->admin);

    $this->postJson("/api/v1/admin/tickets/{$ticket->id}/reply", ['body' => 'internal check', 'is_internal' => true])->assertOk();
    expect(TicketMessage::withoutGlobalScopes()->where('is_internal', true)->count())->toBe(1);

    $this->postJson("/api/v1/admin/tickets/{$ticket->id}/status", ['status' => 'resolved'])
        ->assertOk()->assertJsonPath('data.status', 'resolved');

    $this->postJson("/api/v1/admin/tickets/{$ticket->id}/assign", ['assignee_id' => $agent->id])->assertOk();
    expect($ticket->fresh()->assignee_id)->toBe($agent->id);

    $this->postJson("/api/v1/admin/tickets/{$ticket->id}/escalate")->assertOk();
    expect($ticket->fresh()->escalated_at)->not->toBeNull();
});

it('denies a staff user without handle-tickets', function () {
    $mentor = User::factory()->for($this->tenant)->create(['user_type' => 'staff']);
    $mentor->assignRole('mentor');

    Sanctum::actingAs($mentor);

    $this->getJson('/api/v1/admin/tickets')->assertForbidden();
});

it('404s a foreign tenant ticket', function () {
    $foreign = Ticket::factory()->for(Tenant::factory()->create())->create();

    Sanctum::actingAs($this->admin);

    $this->getJson("/api/v1/admin/tickets/{$foreign->id}")->assertNotFound();
});

it('manages canned responses and ticket routes', function () {
    $route = TicketRoute::factory()->for($this->tenant)->create(['first_response_minutes' => 240]);

    Sanctum::actingAs($this->admin);

    $id = $this->postJson('/api/v1/admin/canned-responses', ['title' => 'Greeting', 'body' => 'Hi there!'])
        ->assertCreated()->json('data.id');
    $this->getJson('/api/v1/admin/canned-responses')->assertOk()->assertJsonFragment(['title' => 'Greeting']);
    $this->deleteJson("/api/v1/admin/canned-responses/{$id}")->assertOk();
    expect(CannedResponse::withoutGlobalScopes()->count())->toBe(0);

    $this->putJson("/api/v1/admin/ticket-routes/{$route->id}", ['first_response_minutes' => 60])
        ->assertOk()->assertJsonPath('data.first_response_minutes', 60);
});
