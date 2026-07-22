<?php

declare(strict_types=1);

use App\Actions\Conversion\CompleteBootcamp;
use App\Models\Message;
use App\Models\MessageTemplate;
use App\Models\Tenant;
use App\Models\Testimonial;
use App\Support\WhatsApp\FakeWhatsAppClient;
use App\Support\WhatsApp\WhatsAppClient;

beforeEach(function () {
    $this->wa = new FakeWhatsAppClient;
    app()->instance(WhatsAppClient::class, $this->wa);
    $this->tenant = Tenant::factory()->create();
    MessageTemplate::factory()->for($this->tenant)->create([
        'key' => 'review_request', 'channel' => 'whatsapp',
        'body' => 'Hi {{name}}, share a testimonial: {{link}}',
    ]);
});

it('requests a testimonial from each attendee when the bootcamp completes', function () {
    ['bootcamp' => $bootcamp] = bootcampConversionSetup($this->tenant);

    withinTenant($this->tenant, fn () => app(CompleteBootcamp::class)->handle($bootcamp));

    expect(Message::withoutGlobalScopes()->where('template_key', 'review_request')->count())->toBe(1)
        ->and($this->wa->sent)->toHaveCount(1);
});

it('does not re-request when a testimonial already exists', function () {
    ['bootcamp' => $bootcamp, 'student' => $student] = bootcampConversionSetup($this->tenant);
    Testimonial::factory()->for($this->tenant)->create([
        'user_id' => $student->id, 'batch_id' => $bootcamp->id, 'stage' => 'bootcamp',
    ]);

    withinTenant($this->tenant, fn () => app(CompleteBootcamp::class)->handle($bootcamp));

    expect(Message::withoutGlobalScopes()->where('template_key', 'review_request')->count())->toBe(0);
});
