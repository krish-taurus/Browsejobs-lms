<?php

declare(strict_types=1);

use App\Models\Message;
use App\Models\MessageTemplate;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
    $this->tenant = Tenant::factory()->create();
    $this->admin = User::factory()->for($this->tenant)->create(['user_type' => 'staff']);
    $this->admin->assignRole('admin');
});

it('lists the delivery log scoped to the tenant', function () {
    Message::factory()->for($this->tenant)->create();
    Message::factory()->for(Tenant::factory()->create())->create();

    Sanctum::actingAs($this->admin);

    $this->getJson('/api/v1/admin/messages')->assertOk()->assertJsonCount(1, 'data');
});

it('creates a template but rejects a banned phrase', function () {
    Sanctum::actingAs($this->admin);

    $this->postJson('/api/v1/admin/message-templates', [
        'key' => 'promo', 'channel' => 'whatsapp', 'category' => 'marketing',
        'body' => 'We offer a guaranteed job!',
    ])->assertStatus(422);

    $this->postJson('/api/v1/admin/message-templates', [
        'key' => 'promo', 'channel' => 'whatsapp', 'category' => 'marketing',
        'body' => 'Join our next masterclass, {{name}}.',
    ])->assertCreated();

    expect(MessageTemplate::withoutGlobalScopes()->where('key', 'promo')->exists())->toBeTrue();
});

it('previews banned-phrase violations', function () {
    Sanctum::actingAs($this->admin);

    $this->postJson('/api/v1/admin/message-templates/preview', ['body' => 'a guaranteed job offer'])
        ->assertOk()->assertJsonPath('data.violations', ['guaranteed job']);
});

it('denies a staff user without manage-messaging', function () {
    $mentor = User::factory()->for($this->tenant)->create(['user_type' => 'staff']);
    $mentor->assignRole('mentor');
    Sanctum::actingAs($mentor);

    $this->getJson('/api/v1/admin/messages')->assertForbidden();
});
