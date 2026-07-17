<?php

declare(strict_types=1);

use App\Enums\TicketPriority;
use App\Models\AuditLog;
use App\Models\Tenant;
use App\Models\Ticket;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
    $this->tenant = Tenant::factory()->create();
    $this->admin = User::factory()->for($this->tenant)->create(['user_type' => 'staff']);
    $this->admin->assignRole('admin');
    $this->student = User::factory()->for($this->tenant)->create(['user_type' => 'student']);

    $this->triaged = Ticket::factory()->for($this->tenant)->create([
        'student_id' => $this->student->id,
        'category' => 'technical',
        'priority' => TicketPriority::High->value,
        'ai_category' => 'payments',
        'ai_urgency' => 'high',
        'ai_sentiment' => 'angry',
        'ai_priority_raised' => true,
        'ai_triaged_at' => now(),
    ]);
});

it('shows staff the triage signals', function () {
    Sanctum::actingAs($this->admin);

    $this->getJson("/api/v1/admin/tickets/{$this->triaged->id}")
        ->assertOk()
        ->assertJsonPath('data.ai.sentiment', 'angry')
        ->assertJsonPath('data.ai.priority_raised', true)
        // The suggestion is worth surfacing precisely because it disagrees.
        ->assertJsonPath('data.ai.category', 'payments')
        ->assertJsonPath('data.ai.category_differs', true);
});

it('never shows a student our sentiment read of them', function () {
    Sanctum::actingAs($this->student);

    // Telling a student "we scored you angry" would be a betrayal, and useless to them.
    $response = $this->getJson("/api/v1/me/tickets/{$this->triaged->id}")->assertOk();

    expect($response->json('data'))->not->toHaveKey('ai');
    expect($response->getContent())->not->toContain('angry');
});

it('keeps the signals out of the student ticket list too', function () {
    Sanctum::actingAs($this->student);

    $response = $this->getJson('/api/v1/me/tickets')->assertOk();

    expect($response->json('data.0'))->not->toHaveKey('ai');
});

it('orders the staff queue by priority so a raise actually moves a ticket', function () {
    $old = Ticket::factory()->for($this->tenant)->create([
        'student_id' => $this->student->id, 'priority' => TicketPriority::Normal->value,
    ]);
    $urgent = Ticket::factory()->for($this->tenant)->create([
        'student_id' => $this->student->id, 'priority' => TicketPriority::Urgent->value,
    ]);

    Sanctum::actingAs($this->admin);

    // $urgent is newest-but-one by id; without priority ordering the newest would lead.
    $ids = collect($this->getJson('/api/v1/admin/tickets')->assertOk()->json('data'))->pluck('id');

    expect($ids->first())->toBe($urgent->id)
        ->and($ids->last())->toBe($old->id);
});

it('lets staff undo an AI priority raise, with an audit entry', function () {
    Sanctum::actingAs($this->admin);

    $this->postJson("/api/v1/admin/tickets/{$this->triaged->id}/clear-ai-priority", ['priority' => 'normal'])
        ->assertOk()
        ->assertJsonPath('data.priority', 'normal')
        ->assertJsonPath('data.ai.priority_raised', false);

    $audit = AuditLog::withoutGlobalScopes()->where('action', 'ticket.ai_priority_cleared')->first();
    expect($audit)->not->toBeNull()
        ->and($audit->actor_id)->toBe($this->admin->id)
        ->and($audit->metadata['from'])->toBe('high');
});

it('does nothing when the priority was not AI-raised', function () {
    $human = Ticket::factory()->for($this->tenant)->create([
        'student_id' => $this->student->id, 'priority' => TicketPriority::Urgent->value, 'ai_priority_raised' => false,
    ]);

    Sanctum::actingAs($this->admin);

    // A human's own urgent must not be silently reset by the undo button.
    $this->postJson("/api/v1/admin/tickets/{$human->id}/clear-ai-priority", ['priority' => 'low'])->assertOk();

    expect($human->fresh()->priority)->toBe(TicketPriority::Urgent)
        ->and(AuditLog::withoutGlobalScopes()->where('action', 'ticket.ai_priority_cleared')->count())->toBe(0);
});

it('validates the restored priority', function () {
    Sanctum::actingAs($this->admin);

    $this->postJson("/api/v1/admin/tickets/{$this->triaged->id}/clear-ai-priority", ['priority' => 'nonsense'])
        ->assertStatus(422);
});

it('404s a foreign tenant ticket on undo', function () {
    $other = Tenant::factory()->create();
    $theirs = Ticket::factory()->for($other)->create(['ai_priority_raised' => true]);

    Sanctum::actingAs($this->admin);

    $this->postJson("/api/v1/admin/tickets/{$theirs->id}/clear-ai-priority", ['priority' => 'normal'])
        ->assertNotFound();
});
