<?php

declare(strict_types=1);

use App\Actions\Conversion\RunBootcampInvites;
use App\Models\Lead;
use App\Models\LeadStage;
use App\Models\Message;
use App\Models\MessageTemplate;
use App\Models\Tenant;
use App\Support\Crm\PhoneNormalizer;
use App\Support\WhatsApp\FakeWhatsAppClient;
use App\Support\WhatsApp\WhatsAppClient;

beforeEach(function () {
    config(['app.frontend_url' => 'https://browsejobs.ai']);
    app()->instance(WhatsAppClient::class, new FakeWhatsAppClient);
    $this->tenant = Tenant::factory()->create();

    foreach ([
        ['key' => 'bootcamp_invite', 'channel' => 'whatsapp', 'subject' => null, 'body' => 'Hi {{name}}, claim your free bootcamp: {{link}}'],
        ['key' => 'bootcamp_invite', 'channel' => 'email', 'subject' => 'Free bootcamp', 'body' => 'Hi {{name}}, claim it: {{link}}'],
    ] as $t) {
        MessageTemplate::factory()->for($this->tenant)->create($t);
    }

    $this->mcStage = withinTenant($this->tenant, fn () => LeadStage::query()->create(['name' => 'Masterclass Registered', 'slug' => 'masterclass-registered', 'position' => 2]));
    $this->bootcampStage = withinTenant($this->tenant, fn () => LeadStage::query()->create(['name' => 'Bootcamp', 'slug' => 'bootcamp', 'position' => 4]));
});

function masterclassLead(Tenant $tenant, LeadStage $stage, string $phone, ?string $email = null): Lead
{
    return withinTenant($tenant, fn () => Lead::factory()->for($tenant)->create([
        'lead_type' => 'masterclass', 'name' => 'Priya', 'phone' => $phone,
        'phone_normalized' => PhoneNormalizer::normalize($phone), 'email' => $email,
        'lead_stage_id' => $stage->id,
    ]));
}

it('invites a masterclass lead to the bootcamp on both channels and advances the stage', function () {
    $lead = masterclassLead($this->tenant, $this->mcStage, '+91 90000 11111', 'priya@example.com');

    $count = app(RunBootcampInvites::class)->handle();

    expect($count)->toBe(1);

    $wa = Message::withoutGlobalScopes()->where('template_key', 'bootcamp_invite')->where('channel', 'whatsapp')->first();
    $email = Message::withoutGlobalScopes()->where('template_key', 'bootcamp_invite')->where('channel', 'email')->first();
    expect($wa)->not->toBeNull()
        ->and($wa->body)->toContain('https://browsejobs.ai/bootcamp')
        ->and($email->recipient)->toBe('priya@example.com')
        ->and($lead->fresh()->lead_stage_id)->toBe($this->bootcampStage->id); // advanced = deduped
});

it('does not re-invite a lead already past the masterclass stage', function () {
    masterclassLead($this->tenant, $this->bootcampStage, '+91 90000 22222'); // already at bootcamp

    expect(app(RunBootcampInvites::class)->handle())->toBe(0)
        ->and(Message::withoutGlobalScopes()->where('template_key', 'bootcamp_invite')->count())->toBe(0);
});

it('is idempotent across runs (stage advance dedups)', function () {
    masterclassLead($this->tenant, $this->mcStage, '+91 90000 33333');

    expect(app(RunBootcampInvites::class)->handle())->toBe(1)
        ->and(app(RunBootcampInvites::class)->handle())->toBe(0); // second run finds nobody at masterclass stage
});
