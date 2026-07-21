<?php

declare(strict_types=1);

use App\Actions\Crm\CaptureLead;
use App\Models\ContactTimelineEvent;
use App\Models\Message;
use App\Models\MessageTemplate;
use App\Models\Tenant;
use App\Support\WhatsApp\FakeWhatsAppClient;
use App\Support\WhatsApp\WhatsAppClient;

beforeEach(function () {
    config(['app.frontend_url' => 'https://browsejobs.ai']);
    $this->wa = new FakeWhatsAppClient;
    app()->instance(WhatsAppClient::class, $this->wa);
    $this->tenant = Tenant::factory()->create();
    MessageTemplate::factory()->for($this->tenant)->create([
        'key' => 'lead_welcome', 'channel' => 'whatsapp', 'body' => 'Hi {{name}}, join your free masterclass: {{link}}',
    ]);
    MessageTemplate::factory()->for($this->tenant)->create([
        'key' => 'lead_welcome', 'channel' => 'email', 'subject' => 'Your free masterclass', 'body' => 'Hi {{name}}, join here: {{link}}',
    ]);
});

it('sends a WhatsApp welcome carrying the masterclass link when a lead is captured', function () {
    $lead = app(CaptureLead::class)->handle($this->tenant, [
        'lead_type' => 'masterclass', 'name' => 'Priya Sharma', 'phone' => '+91 90000 12345',
    ]);

    $message = Message::withoutGlobalScopes()->where('template_key', 'lead_welcome')->where('channel', 'whatsapp')->first();
    expect($message)->not->toBeNull()
        ->and($message->lead_id)->toBe($lead->id)
        ->and($message->body)->toContain('Priya Sharma')
        ->and($message->body)->toContain('https://browsejobs.ai/masterclass') // no longer a dead-end
        ->and($this->wa->sent)->toHaveCount(1);

    expect(ContactTimelineEvent::withoutGlobalScopes()
        ->where('lead_id', $lead->id)->where('type', ContactTimelineEvent::TYPE_MESSAGE_OUT)->count())->toBe(1);
});

it('also emails the masterclass link when the lead left an email', function () {
    app(CaptureLead::class)->handle($this->tenant, [
        'lead_type' => 'masterclass', 'name' => 'Ravi', 'phone' => '+91 90000 22222', 'email' => 'ravi@example.com',
    ]);

    $email = Message::withoutGlobalScopes()->where('template_key', 'lead_welcome')->where('channel', 'email')->first();
    expect($email)->not->toBeNull()
        ->and($email->recipient)->toBe('ravi@example.com')
        ->and($email->body)->toContain('https://browsejobs.ai/masterclass');
});
