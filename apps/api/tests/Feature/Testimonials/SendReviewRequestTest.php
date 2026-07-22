<?php

declare(strict_types=1);

use App\Actions\Reviews\SendReviewRequest;
use App\Enums\ReviewStage;
use App\Models\Message;
use App\Models\MessageTemplate;
use App\Models\Tenant;
use App\Models\Testimonial;
use App\Models\User;
use App\Support\Tenancy\TenantContext;
use App\Support\WhatsApp\FakeWhatsAppClient;
use App\Support\WhatsApp\WhatsAppClient;

beforeEach(function () {
    app()->instance(WhatsAppClient::class, new FakeWhatsAppClient);
    $this->tenant = Tenant::factory()->create();
    MessageTemplate::factory()->for($this->tenant)->create([
        'key' => 'review_request', 'channel' => 'whatsapp', 'body' => 'Hi {{name}}: {{link}}',
    ]);
    $this->student = User::factory()->for($this->tenant)->create(['user_type' => 'student', 'phone' => '+91 90000 34343']);
});

function sendReviewCount(): int
{
    return Message::withoutGlobalScopes()->where('template_key', 'review_request')->count();
}

it('sends a review request for a stage', function () {
    $sent = withinTenant($this->tenant, fn () => app(SendReviewRequest::class)->handle($this->student, ReviewStage::Placement));

    expect($sent)->toBeTrue()->and(sendReviewCount())->toBe(1);
});

it('does not re-prompt a stage the student already engaged with', function () {
    Testimonial::factory()->for($this->tenant)->create([
        'user_id' => $this->student->id, 'stage' => 'placement', 'status' => 'private_feedback',
    ]);

    $sent = withinTenant($this->tenant, fn () => app(SendReviewRequest::class)->handle($this->student, ReviewStage::Placement));

    expect($sent)->toBeFalse()->and(sendReviewCount())->toBe(0);
});

it('sends independently per stage', function () {
    Testimonial::factory()->for($this->tenant)->create([
        'user_id' => $this->student->id, 'stage' => 'bootcamp', 'status' => 'approved',
    ]);

    $sent = withinTenant($this->tenant, fn () => app(SendReviewRequest::class)->handle($this->student, ReviewStage::CourseComplete));

    expect($sent)->toBeTrue()->and(sendReviewCount())->toBe(1);
});

it('is a no-op when review requests are disabled', function () {
    config(['vouchers.review_request.enabled' => false]);

    $sent = withinTenant($this->tenant, fn () => app(SendReviewRequest::class)->handle($this->student, ReviewStage::Placement));

    expect($sent)->toBeFalse()->and(sendReviewCount())->toBe(0);
});
