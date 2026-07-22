<?php

declare(strict_types=1);

use App\Actions\Conversion\CompleteMasterclass;
use App\Models\Batch;
use App\Models\BatchMember;
use App\Models\Message;
use App\Models\MessageTemplate;
use App\Models\Tenant;
use App\Models\Testimonial;
use App\Models\User;
use App\Support\WhatsApp\FakeWhatsAppClient;
use App\Support\WhatsApp\WhatsAppClient;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    app()->instance(WhatsAppClient::class, new FakeWhatsAppClient);
    $this->tenant = Tenant::factory()->create();
    MessageTemplate::factory()->for($this->tenant)->create([
        'key' => 'review_request', 'channel' => 'whatsapp', 'body' => 'Hi {{name}}: {{link}}',
    ]);
    $this->masterclass = Batch::factory()->for($this->tenant)->create(['type' => 'masterclass']);
    $this->student = User::factory()->for($this->tenant)->create(['user_type' => 'student', 'phone' => '+91 90000 77777']);
    BatchMember::factory()->for($this->tenant)->create([
        'batch_id' => $this->masterclass->id, 'user_id' => $this->student->id,
    ]);
});

function reviewRequests(): int
{
    return Message::withoutGlobalScopes()->where('template_key', 'review_request')->count();
}

it('requests a review from each attendee when the masterclass completes', function () {
    $attendees = withinTenant($this->tenant, fn () => app(CompleteMasterclass::class)->handle($this->masterclass));

    expect($attendees)->toBe(1)
        ->and(reviewRequests())->toBe(1)
        ->and($this->masterclass->refresh()->status)->toBe('completed');
});

it('does not re-request when the attendee already engaged with the masterclass stage', function () {
    Testimonial::factory()->for($this->tenant)->create([
        'user_id' => $this->student->id, 'stage' => 'masterclass', 'status' => 'private_feedback',
    ]);

    withinTenant($this->tenant, fn () => app(CompleteMasterclass::class)->handle($this->masterclass));

    expect(reviewRequests())->toBe(0);
});

it('refuses to complete a non-masterclass batch this way', function () {
    $bootcamp = Batch::factory()->for($this->tenant)->create(['type' => 'bootcamp']);

    withinTenant($this->tenant, fn () => app(CompleteMasterclass::class)->handle($bootcamp));
})->throws(ValidationException::class);
