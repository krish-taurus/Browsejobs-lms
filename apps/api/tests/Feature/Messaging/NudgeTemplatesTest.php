<?php

declare(strict_types=1);

use App\Enums\MessageStatus;
use App\Models\MessageTemplate;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Messaging\Messenger;
use App\Support\WhatsApp\FakeWhatsAppClient;
use App\Support\WhatsApp\WhatsAppClient;
use Database\Seeders\MessagingSeeder;

beforeEach(function () {
    app()->instance(WhatsAppClient::class, new FakeWhatsAppClient);
    // MessagingSeeder seeds the browsejobs tenant by slug.
    $this->tenant = Tenant::factory()->create(['slug' => 'browsejobs']);
    $this->seed(MessagingSeeder::class);
    $this->student = User::factory()->for($this->tenant)->create([
        'user_type' => 'student', 'phone' => '+91 90000 12345', 'email' => 'stu@example.com',
    ]);
});

/** The three keys the P4.7/P4.8 daily nudge commands send. */
$keys = ['alumni.checkin', 'jobs.nudge', 'application.followup'];

it('seeds a WhatsApp + email template for every nudge key', function () use ($keys) {
    foreach ($keys as $key) {
        foreach (['whatsapp', 'email'] as $channel) {
            expect(MessageTemplate::withoutGlobalScopes()->where('key', $key)->where('channel', $channel)->where('active', true)->exists())
                ->toBeTrue("missing template {$key}/{$channel}");
        }
    }
});

it('sends the jobs.nudge instead of silently dropping it', function () {
    $message = withinTenant($this->tenant, fn () => app(Messenger::class)->send(
        $this->student, 'jobs.nudge', ['name' => 'Asha', 'count' => '3', 'role' => 'Data Engineer'],
    ));

    expect($message)->not->toBeNull()
        ->and($message->status)->toBe(MessageStatus::Sent)
        ->and($message->body)->toContain('3')
        ->and($message->body)->toContain('Data Engineer');
});

it('sends the alumni.checkin nudge', function () {
    $message = withinTenant($this->tenant, fn () => app(Messenger::class)->send(
        $this->student, 'alumni.checkin', ['name' => 'Asha', 'months' => '6'],
    ));

    expect($message)->not->toBeNull()
        ->and($message->status)->toBe(MessageStatus::Sent)
        ->and($message->body)->toContain('6 months');
});

it('sends the application.followup nudge', function () {
    $message = withinTenant($this->tenant, fn () => app(Messenger::class)->send(
        $this->student, 'application.followup', ['name' => 'Asha', 'role' => 'Data Analyst'],
    ));

    expect($message)->not->toBeNull()
        ->and($message->status)->toBe(MessageStatus::Sent)
        ->and($message->body)->toContain('Data Analyst');
});

it('passes the brand-voice linter (no banned phrases)', function () use ($keys) {
    // The Messenger blocks banned phrases; a successful Sent proves the copy is clean.
    foreach ($keys as $key) {
        $vars = ['name' => 'Asha', 'months' => '12', 'count' => '2', 'role' => 'QA Engineer'];
        $message = withinTenant($this->tenant, fn () => app(Messenger::class)->send($this->student, $key, $vars));
        expect($message?->status)->toBe(MessageStatus::Sent, "{$key} was blocked or dropped");
    }
});
