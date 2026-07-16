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
    $this->wa = new FakeWhatsAppClient;
    app()->instance(WhatsAppClient::class, $this->wa);
    $this->tenant = Tenant::factory()->create();
    MessageTemplate::factory()->for($this->tenant)->create([
        'key' => 'lead_welcome', 'channel' => 'whatsapp', 'body' => 'Hi {{name}}, welcome to BrowseJobs.',
    ]);
});

it('sends a WhatsApp welcome when a lead is captured', function () {
    $lead = app(CaptureLead::class)->handle($this->tenant, [
        'lead_type' => 'masterclass', 'name' => 'Priya Sharma', 'phone' => '+91 90000 12345',
    ]);

    $message = Message::withoutGlobalScopes()->where('template_key', 'lead_welcome')->first();
    expect($message)->not->toBeNull()
        ->and($message->lead_id)->toBe($lead->id)
        ->and($message->body)->toContain('Priya Sharma')
        ->and($this->wa->sent)->toHaveCount(1);

    expect(ContactTimelineEvent::withoutGlobalScopes()
        ->where('lead_id', $lead->id)->where('type', ContactTimelineEvent::TYPE_MESSAGE_OUT)->count())->toBe(1);
});
