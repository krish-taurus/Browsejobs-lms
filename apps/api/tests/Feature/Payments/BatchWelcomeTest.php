<?php

declare(strict_types=1);

use App\Actions\Payments\CreateFeePlan;
use App\Enums\FeePlanType;
use App\Events\PaymentCaptured;
use App\Listeners\SendBatchWelcome;
use App\Models\InAppNotification;
use App\Models\Message;
use App\Models\MessageTemplate;
use App\Models\Payment;
use App\Models\Tenant;
use App\Models\User;
use App\Support\WhatsApp\FakeWhatsAppClient;
use App\Support\WhatsApp\WhatsAppClient;

beforeEach(function () {
    config(['app.frontend_url' => 'https://browsejobs.ai']);
    app()->instance(WhatsAppClient::class, new FakeWhatsAppClient);
    $this->tenant = Tenant::factory()->create();

    foreach ([
        ['key' => 'batch_welcome', 'channel' => 'whatsapp', 'subject' => null, 'body' => 'Welcome {{name}} to {{batch}} (starts {{starts}}): {{link}}'],
        ['key' => 'batch_welcome', 'channel' => 'email', 'subject' => 'Welcome to {{batch}}', 'body' => 'Welcome {{name}} to {{batch}}: {{link}}'],
        ['key' => 'batch_new_enrollee', 'channel' => 'inapp', 'subject' => 'New enrollment', 'body' => '{{student}} enrolled in {{batch}}.'],
    ] as $t) {
        MessageTemplate::factory()->for($this->tenant)->create($t);
    }

    $setup = reservedMemberIn($this->tenant, '+91 90000 12345', 'stu@example.com');
    $this->batch = $setup['batch'];
    $this->student = $setup['student'];

    // Give the batch a trainer so we can assert the in-the-loop ping.
    $this->trainer = User::factory()->for($this->tenant)->create(['user_type' => 'staff', 'name' => 'Coach Anil']);
    $this->batch->update(['trainer_id' => $this->trainer->id]);

    [$this->plan, $this->inst1] = withinTenant($this->tenant, function () {
        $plan = app(CreateFeePlan::class)->handle($this->tenant, $this->student, $this->batch, FeePlanType::Emi, 3);

        return [$plan, $plan->instalments()->where('seq', 1)->firstOrFail()];
    });
});

function fireCapture(Tenant $tenant, $instalment): void
{
    $payment = withinTenant($tenant, fn () => Payment::factory()->for($tenant)->create([
        'instalment_id' => $instalment->id,
        'amount_paise' => 1000000,
    ]));

    withinTenant($tenant, fn () => app(SendBatchWelcome::class)->handle(new PaymentCaptured($payment, $instalment)));
}

it('welcomes the student on both channels and pings the trainer on first payment', function () {
    fireCapture($this->tenant, $this->inst1);

    $wa = Message::withoutGlobalScopes()->where('template_key', 'batch_welcome')->where('channel', 'whatsapp')->first();
    $email = Message::withoutGlobalScopes()->where('template_key', 'batch_welcome')->where('channel', 'email')->first();

    expect($wa)->not->toBeNull()
        ->and($wa->body)->toContain($this->student->name)
        ->and($wa->body)->toContain('https://browsejobs.ai/dashboard')
        ->and($email)->not->toBeNull()
        ->and($email->recipient)->toBe('stu@example.com');

    // Trainer got an in-app new-enrollee notification.
    $note = InAppNotification::withoutGlobalScopes()->where('user_id', $this->trainer->id)->first();
    expect($note)->not->toBeNull()
        ->and($note->body)->toContain($this->student->name);
});

it('does not welcome again on later instalments', function () {
    $inst2 = withinTenant($this->tenant, fn () => $this->plan->instalments()->where('seq', 2)->firstOrFail());

    fireCapture($this->tenant, $inst2);

    expect(Message::withoutGlobalScopes()->where('template_key', 'batch_welcome')->count())->toBe(0);
});

it('does nothing when the batch has no trainer (student still welcomed)', function () {
    withinTenant($this->tenant, fn () => $this->batch->update(['trainer_id' => null]));

    fireCapture($this->tenant, $this->inst1);

    expect(Message::withoutGlobalScopes()->where('template_key', 'batch_welcome')->count())->toBe(2) // wa + email
        ->and(InAppNotification::withoutGlobalScopes()->count())->toBe(0);
});
