<?php

declare(strict_types=1);

use App\Enums\MessageStatus;
use App\Events\LeadReplied;
use App\Models\ContactTimelineEvent;
use App\Models\Lead;
use App\Models\Message;
use App\Models\Tenant;
use App\Support\Crm\PhoneNormalizer;
use Illuminate\Support\Facades\Event;

beforeEach(function () {
    $this->tenant = Tenant::factory()->create(['slug' => 'watenant']);
    config([
        'services.whatsapp.app_secret' => 'wa-secret',
        'services.whatsapp.verify_token' => 'verify-me',
        'services.whatsapp.tenant_slug' => 'watenant',
    ]);
});

function postWhatsApp(array $payload)
{
    $body = json_encode($payload);
    $signature = 'sha256='.hash_hmac('sha256', $body, 'wa-secret');

    return test()->call('POST', '/api/webhooks/whatsapp', [], [], [], [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_X_HUB_SIGNATURE_256' => $signature,
    ], $body);
}

function inboundPayload(string $from, string $text): array
{
    return ['object' => 'whatsapp_business_account', 'entry' => [[
        'changes' => [[
            'field' => 'messages',
            'value' => ['messages' => [[
                'from' => $from, 'id' => 'wamid.IN1', 'type' => 'text', 'text' => ['body' => $text],
            ]]],
        ]],
    ]]];
}

it('answers the subscription handshake', function () {
    $this->get('/api/webhooks/whatsapp?hub_mode=subscribe&hub_verify_token=verify-me&hub_challenge=xyz')
        ->assertOk()->assertSee('xyz');
});

it('rejects an unsigned inbound delivery', function () {
    $this->postJson('/api/webhooks/whatsapp', inboundPayload('919000012345', 'hi'))->assertForbidden();
});

it('rejects a wrong signature', function () {
    test()->call('POST', '/api/webhooks/whatsapp', [], [], [], [
        'CONTENT_TYPE' => 'application/json', 'HTTP_X_HUB_SIGNATURE_256' => 'sha256=bad',
    ], json_encode(inboundPayload('919000012345', 'hi')))->assertForbidden();
});

it('records an inbound reply on the timeline and flags the lead', function () {
    Event::fake([LeadReplied::class]);
    $lead = Lead::factory()->for($this->tenant)->create([
        'phone' => '+91 90000 12345', 'phone_normalized' => PhoneNormalizer::normalize('+91 90000 12345'),
    ]);

    postWhatsApp(inboundPayload('919000012345', 'Yes, interested!'))->assertOk()->assertJson(['received' => 1]);

    expect(Message::withoutGlobalScopes()->where('direction', 'in')->where('lead_id', $lead->id)->count())->toBe(1)
        ->and(ContactTimelineEvent::withoutGlobalScopes()->where('lead_id', $lead->id)->where('type', ContactTimelineEvent::TYPE_MESSAGE_IN)->count())->toBe(1)
        ->and($lead->fresh()->last_replied_at)->not->toBeNull();

    Event::assertDispatched(LeadReplied::class);
});

it('applies delivery + read status updates to the message log', function () {
    $message = Message::factory()->for($this->tenant)->create(['provider_message_id' => 'wamid.OUT1', 'status' => 'sent']);

    postWhatsApp(['object' => 'whatsapp_business_account', 'entry' => [[
        'changes' => [['field' => 'messages', 'value' => ['statuses' => [
            ['id' => 'wamid.OUT1', 'status' => 'read'],
        ]]]],
    ]]])->assertOk();

    expect($message->fresh()->status)->toBe(MessageStatus::Read);
});
