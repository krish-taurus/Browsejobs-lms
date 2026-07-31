<?php

declare(strict_types=1);

use App\Jobs\SendWhatsAppMessage;
use App\Models\Message;
use App\Models\MessageTemplate;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Messaging\Messenger;
use App\Support\WhatsApp\FakeWhatsAppClient;
use App\Support\WhatsApp\WhatsAppClient;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    $this->wa = new FakeWhatsAppClient;
    app()->instance(WhatsAppClient::class, $this->wa);

    $this->tenant = Tenant::factory()->create();
});

it('sends an activated key as an approved template with ordered params', function () {
    withinTenant($this->tenant, function (): void {
        // Activated: message_templates.name carries the approved Meta template.
        MessageTemplate::query()->create([
            'tenant_id' => $this->tenant->id, 'key' => 'batch_credentials', 'channel' => 'whatsapp',
            'category' => 'utility', 'name' => 'bj_batch_joined',
            'body' => 'Hi {{name}}, batch {{batch}} starts {{starts}} — sign in at {{link}}',
        ]);

        $student = User::factory()->for($this->tenant)->create(['user_type' => 'student', 'phone' => '+919000077770']);

        app(Messenger::class)->send($student, 'batch_credentials', [
            'name' => 'Asha', 'batch' => 'DE-202608-01', 'course' => 'Data Engineering',
            'batch_course' => 'DE-202608-01 (Data Engineering)', 'starts' => 'Sat, 01 Aug',
            'login' => 'asha@example.com', 'link' => 'http://localhost:3000/student',
        ], ['channel' => \App\Enums\MessageChannel::WhatsApp]);

        $message = Message::query()->latest('id')->first();
        SendWhatsAppMessage::dispatchSync($message->id);

        expect($this->wa->sent)->toHaveCount(1)
            ->and($this->wa->sent[0]['template'])->toBe('bj_batch_joined')
            // Ordered per config/whatsapp_templates.php — course rides inside
            // the batch slot; no login, no password.
            ->and($this->wa->sent[0]['params'])->toBe(['Asha', 'DE-202608-01 (Data Engineering)', 'Sat, 01 Aug']);
    });
});

it('sends an image card while no template is active and a banner is configured', function () {
    config(['services.whatsapp.card_image_id' => '1050793324085431']);

    withinTenant($this->tenant, function (): void {
        MessageTemplate::query()->create([
            'tenant_id' => $this->tenant->id, 'key' => 'batch_credentials', 'channel' => 'whatsapp',
            'category' => 'utility', 'name' => null,
            'body' => 'Hi {{name}}, batch {{batch}}',
        ]);

        $student = User::factory()->for($this->tenant)->create(['user_type' => 'student', 'phone' => '+919000077772']);

        app(Messenger::class)->send($student, 'batch_credentials', [
            'name' => 'Asha', 'batch' => 'DE-202608-01',
        ], ['channel' => \App\Enums\MessageChannel::WhatsApp]);

        SendWhatsAppMessage::dispatchSync(Message::query()->latest('id')->first()->id);

        expect($this->wa->sent[0]['image'] ?? null)->toBe('1050793324085431')
            ->and($this->wa->sent[0]['body'])->toContain('DE-202608-01');
    });
});

it('falls back to a session text while the template is not yet activated', function () {
    withinTenant($this->tenant, function (): void {
        MessageTemplate::query()->create([
            'tenant_id' => $this->tenant->id, 'key' => 'batch_credentials', 'channel' => 'whatsapp',
            'category' => 'utility', 'name' => null, // not approved yet
            'body' => 'Hi {{name}}, batch {{batch}}',
        ]);

        $student = User::factory()->for($this->tenant)->create(['user_type' => 'student', 'phone' => '+919000077771']);

        app(Messenger::class)->send($student, 'batch_credentials', [
            'name' => 'Asha', 'batch' => 'DE-202608-01',
        ], ['channel' => \App\Enums\MessageChannel::WhatsApp]);

        SendWhatsAppMessage::dispatchSync(Message::query()->latest('id')->first()->id);

        expect($this->wa->sent[0]['template'])->toBeNull()
            ->and($this->wa->sent[0]['params'])->toBe([]);
    });
});

it('activates approved templates from Meta and deactivates unapproved ones', function () {
    config([
        'services.whatsapp.business_account_id' => '38256631457269640',
        'services.whatsapp.access_token' => 'token',
    ]);

    Http::fake([
        'graph.facebook.com/*' => Http::response(['data' => [
            ['name' => 'bj_batch_joined', 'status' => 'APPROVED', 'language' => 'en'],
            ['name' => 'bj_class_scheduled', 'status' => 'PENDING', 'language' => 'en'],
        ]]),
    ]);

    withinTenant($this->tenant, function (): void {
        MessageTemplate::query()->create([
            'tenant_id' => $this->tenant->id, 'key' => 'batch_credentials', 'channel' => 'whatsapp',
            'category' => 'utility', 'body' => 'x',
        ]);
        MessageTemplate::query()->create([
            'tenant_id' => $this->tenant->id, 'key' => 'class_scheduled', 'channel' => 'whatsapp',
            'category' => 'utility', 'name' => 'bj_class_scheduled', 'body' => 'x', // was active, Meta paused it
        ]);
    });

    $this->artisan('whatsapp:sync-templates')->assertSuccessful();

    withinTenant($this->tenant, function (): void {
        expect(MessageTemplate::query()->where('key', 'batch_credentials')->where('channel', 'whatsapp')->value('name'))
            ->toBe('bj_batch_joined')
            ->and(MessageTemplate::query()->where('key', 'class_scheduled')->where('channel', 'whatsapp')->value('name'))
            ->toBeNull();
    });
});
