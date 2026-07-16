<?php

declare(strict_types=1);

use App\Enums\MessageStatus;
use App\Models\ContactTimelineEvent;
use App\Models\Lead;
use App\Models\Message;
use App\Models\MessagePreference;
use App\Models\MessageTemplate;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Crm\PhoneNormalizer;
use App\Support\Messaging\Messenger;
use App\Support\WhatsApp\FakeWhatsAppClient;
use App\Support\WhatsApp\WhatsAppClient;
use Illuminate\Support\Carbon;

beforeEach(function () {
    $this->wa = new FakeWhatsAppClient;
    app()->instance(WhatsAppClient::class, $this->wa);

    $this->tenant = Tenant::factory()->create();
    $this->student = User::factory()->for($this->tenant)->create([
        'user_type' => 'student', 'phone' => '+91 90000 12345', 'email' => 'stu@example.com',
    ]);
});

function template(Tenant $tenant, string $key, string $body, string $category = 'utility', string $channel = 'whatsapp'): MessageTemplate
{
    return MessageTemplate::factory()->for($tenant)->create([
        'key' => $key, 'channel' => $channel, 'category' => $category, 'body' => $body,
    ]);
}

it('renders vars + a magic link, logs the message, sends over WhatsApp, and lands on the lead timeline', function () {
    template($this->tenant, 'greet', 'Hi {{name}}, join: {{link}}');
    $lead = Lead::factory()->for($this->tenant)->create([
        'phone' => $this->student->phone,
        'phone_normalized' => PhoneNormalizer::normalize($this->student->phone),
    ]);

    $message = withinTenant($this->tenant, fn () => app(Messenger::class)->send(
        $this->student, 'greet', ['name' => 'Priya'], ['magic' => ['action' => 'join-class']],
    ));

    expect($message->status)->toBe(MessageStatus::Sent)
        ->and($message->provider_message_id)->toStartWith('wamid.')
        ->and($this->wa->sent)->toHaveCount(1)
        ->and($this->wa->sent[0]['body'])->toContain('Priya')
        ->and($this->wa->sent[0]['body'])->toContain('/magic/');

    expect(ContactTimelineEvent::withoutGlobalScopes()
        ->where('lead_id', $lead->id)->where('type', ContactTimelineEvent::TYPE_MESSAGE_OUT)->count())->toBe(1);
});

it('suppresses a marketing message when the student has not opted in', function () {
    template($this->tenant, 'promo', 'Special offer {{name}}', 'marketing');

    $message = withinTenant($this->tenant, fn () => app(Messenger::class)->send($this->student, 'promo', ['name' => 'A']));

    expect($message->status)->toBe(MessageStatus::Suppressed)
        ->and($message->suppressed_reason)->toBe('not_opted_in')
        ->and($this->wa->sent)->toHaveCount(0);
});

it('utility bypasses quiet hours; marketing is suppressed during them', function () {
    $this->travelTo(Carbon::parse('2026-08-01 17:30:00', 'UTC')); // 23:00 IST — quiet
    template($this->tenant, 'ping', 'Hi {{name}}', 'utility');
    template($this->tenant, 'promo', 'Offer {{name}}', 'marketing');
    MessagePreference::factory()->for($this->tenant)->create(['user_id' => $this->student->id, 'marketing_opt_in' => true]);

    $utility = withinTenant($this->tenant, fn () => app(Messenger::class)->send($this->student, 'ping', ['name' => 'A']));
    $marketing = withinTenant($this->tenant, fn () => app(Messenger::class)->send($this->student, 'promo', ['name' => 'A']));

    expect($utility->status)->toBe(MessageStatus::Sent)
        ->and($marketing->status)->toBe(MessageStatus::Suppressed)
        ->and($marketing->suppressed_reason)->toBe('quiet_hours');

    $this->travelBack();
});

it('suppresses marketing past the frequency cap but lets utility through', function () {
    $this->travelTo(Carbon::parse('2026-08-01 06:30:00', 'UTC')); // 12:00 IST — open
    MessagePreference::factory()->for($this->tenant)->create(['user_id' => $this->student->id, 'marketing_opt_in' => true]);
    Message::factory()->for($this->tenant)->count(6)->create(['user_id' => $this->student->id, 'status' => 'sent']);
    template($this->tenant, 'promo', 'Offer {{name}}', 'marketing');
    template($this->tenant, 'ping', 'Hi {{name}}', 'utility');

    $marketing = withinTenant($this->tenant, fn () => app(Messenger::class)->send($this->student, 'promo', ['name' => 'A']));
    $utility = withinTenant($this->tenant, fn () => app(Messenger::class)->send($this->student, 'ping', ['name' => 'A']));

    expect($marketing->status)->toBe(MessageStatus::Suppressed)
        ->and($marketing->suppressed_reason)->toBe('frequency_cap')
        ->and($utility->status)->toBe(MessageStatus::Sent);

    $this->travelBack();
});

it('blocks a message whose rendered body contains a banned phrase', function () {
    template($this->tenant, 'bad', 'We offer a guaranteed job to {{name}}!');

    $message = withinTenant($this->tenant, fn () => app(Messenger::class)->send($this->student, 'bad', ['name' => 'A']));

    expect($message->status)->toBe(MessageStatus::Blocked)
        ->and($message->suppressed_reason)->toBe('banned_phrase')
        ->and($this->wa->sent)->toHaveCount(0);
});

it('routes to the student\'s preferred channel', function () {
    template($this->tenant, 'greet', 'email body {{name}}', 'utility', 'email');
    MessagePreference::factory()->for($this->tenant)->create([
        'user_id' => $this->student->id, 'preferred_channel' => 'email',
    ]);

    $message = withinTenant($this->tenant, fn () => app(Messenger::class)->send($this->student, 'greet', ['name' => 'A']));

    expect($message->channel->value)->toBe('email')
        ->and($message->recipient)->toBe('stu@example.com');
});
