<?php

declare(strict_types=1);

use App\Actions\Support\CreateTicket;
use App\Enums\TicketCategory;
use App\Models\Message;
use App\Models\MessageTemplate;
use App\Models\SupportTeam;
use App\Models\Tenant;
use App\Models\TicketRoute;
use App\Models\User;
use App\Support\WhatsApp\FakeWhatsAppClient;
use App\Support\WhatsApp\WhatsAppClient;

beforeEach(function () {
    app()->instance(WhatsAppClient::class, new FakeWhatsAppClient);
    $this->tenant = Tenant::factory()->create();
    $this->student = User::factory()->for($this->tenant)->create(['user_type' => 'student', 'phone' => '+91 90000 60001']);

    foreach (['ticket_created_ack', 'ticket_assigned'] as $key) {
        MessageTemplate::factory()->for($this->tenant)->create([
            'key' => $key, 'channel' => 'whatsapp', 'body' => "{$key}: {{ref}} {{link}}",
        ]);
    }
});

it('acks the student and pings the assignee when a ticket is created', function () {
    $team = SupportTeam::query()->create(['tenant_id' => $this->tenant->id, 'name' => 'Tech', 'slug' => 'technical', 'category' => 'technical']);
    $agent = User::factory()->for($this->tenant)->create(['user_type' => 'staff', 'phone' => '+91 90000 60002']);
    $team->members()->attach($agent->id);
    TicketRoute::factory()->for($this->tenant)->create([
        'category' => TicketCategory::Technical->value, 'routing' => TicketRoute::ROUTING_TEAM, 'support_team_id' => $team->id,
    ]);

    app(CreateTicket::class)->handle($this->student, TicketCategory::Technical, 'Help', 'my issue in detail');

    expect(Message::withoutGlobalScopes()->where('template_key', 'ticket_created_ack')->count())->toBe(1)
        ->and(Message::withoutGlobalScopes()->where('template_key', 'ticket_assigned')->count())->toBe(1);
});
