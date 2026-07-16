<?php

declare(strict_types=1);

use App\Actions\Payments\CreateFeePlan;
use App\Actions\Payments\SendPaymentLink;
use App\Enums\FeePlanType;
use App\Models\Instalment;
use App\Models\Message;
use App\Models\MessageTemplate;
use App\Models\Tenant;
use App\Support\Razorpay\FakeRazorpayClient;
use App\Support\Razorpay\RazorpayClient;
use App\Support\WhatsApp\FakeWhatsAppClient;
use App\Support\WhatsApp\WhatsAppClient;

beforeEach(function () {
    app()->instance(RazorpayClient::class, new FakeRazorpayClient);
    $this->wa = new FakeWhatsAppClient;
    app()->instance(WhatsAppClient::class, $this->wa);
    $this->tenant = Tenant::factory()->create();
});

it('creates the link and auto-delivers it via the messaging hub', function () {
    ['batch' => $batch, 'student' => $student] = reservedMemberIn($this->tenant);
    MessageTemplate::factory()->for($this->tenant)->create([
        'key' => 'payment_link', 'channel' => 'whatsapp', 'body' => 'Hi {{name}}, pay here: {{link}}',
    ]);

    $instalment = withinTenant($this->tenant, function () use ($batch, $student) {
        $plan = app(CreateFeePlan::class)->handle($this->tenant, $student, $batch, FeePlanType::Single, 1);

        return app(SendPaymentLink::class)->handle($plan->instalments()->firstOrFail());
    });

    expect($instalment->payment_link_url)->toContain('rzp.test');

    $message = Message::withoutGlobalScopes()->where('template_key', 'payment_link')->first();
    expect($message)->not->toBeNull()
        ->and($message->body)->toContain($instalment->payment_link_url)
        ->and($this->wa->sent)->toHaveCount(1);
});

it('no-ops delivery when no payment_link template exists', function () {
    ['batch' => $batch, 'student' => $student] = reservedMemberIn($this->tenant);

    withinTenant($this->tenant, function () use ($batch, $student) {
        $plan = app(CreateFeePlan::class)->handle($this->tenant, $student, $batch, FeePlanType::Single, 1);
        app(SendPaymentLink::class)->handle($plan->instalments()->firstOrFail());
    });

    expect(Message::withoutGlobalScopes()->count())->toBe(0)
        ->and(Instalment::withoutGlobalScopes()->whereNotNull('payment_link_url')->count())->toBe(1);
});
