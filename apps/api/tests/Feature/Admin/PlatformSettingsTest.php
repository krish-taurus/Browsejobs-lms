<?php

declare(strict_types=1);

use App\Models\AuditLog;
use App\Models\PlatformSetting;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Settings\PlatformSettings;
use Database\Seeders\RolePermissionSeeder;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
    $this->tenant = Tenant::factory()->create();
    $this->super = User::factory()->for($this->tenant)->create(['user_type' => 'staff']);
    $this->super->assignRole('super-admin');
});

it('returns the settings schema with no secret values', function () {
    PlatformSetting::query()->create(['group' => 'ai', 'key' => 'openai_api_key', 'value' => 'sk-secret-1234']);
    PlatformSetting::query()->create(['group' => 'ai', 'key' => 'provider', 'value' => 'openai']);

    Sanctum::actingAs($this->super);
    $body = $this->getJson('/api/v1/admin/settings')->assertOk();

    $ai = collect($body->json('data'))->firstWhere('key', 'ai');
    $keyField = collect($ai['fields'])->firstWhere('key', 'openai_api_key');
    $providerField = collect($ai['fields'])->firstWhere('key', 'provider');

    // The secret is set, shown only as a masked hint — never in full.
    expect($keyField['set'])->toBeTrue()
        ->and($keyField['value'])->toBeNull()
        ->and($keyField['preview'])->toBe('••••1234');
    // A non-secret (the provider choice) round-trips its value.
    expect($providerField['value'])->toBe('openai');

    // And the raw secret appears nowhere in the response.
    expect($body->getContent())->not->toContain('sk-secret-1234');
});

it('stores a submitted secret encrypted at rest', function () {
    Sanctum::actingAs($this->super);

    $this->putJson('/api/v1/admin/settings', ['settings' => ['ai' => ['anthropic_api_key' => 'sk-ant-live-9999']]])
        ->assertOk();

    $row = PlatformSetting::query()->where('group', 'ai')->where('key', 'anthropic_api_key')->firstOrFail();
    // The decrypted accessor returns the value...
    expect($row->value)->toBe('sk-ant-live-9999');
    // ...but the column itself is ciphertext, not the plaintext key.
    $raw = DB::table('platform_settings')->where('id', $row->id)->value('value');
    expect($raw)->not->toContain('sk-ant-live-9999');
});

it('leaves an existing secret untouched when the field is submitted blank', function () {
    PlatformSetting::query()->create(['group' => 'whatsapp', 'key' => 'access_token', 'value' => 'existing-token']);

    Sanctum::actingAs($this->super);
    // The form never shows the current secret, so a blank box must NOT clear it.
    $this->putJson('/api/v1/admin/settings', ['settings' => ['whatsapp' => ['access_token' => '']]])->assertOk();

    expect(PlatformSetting::query()->where('key', 'access_token')->value('value'))->toBe('existing-token');
});

it('applies stored keys over config so the clients use them', function () {
    config(['ai.provider' => 'anthropic', 'services.whatsapp.access_token' => 'env-default']);

    PlatformSetting::query()->create(['group' => 'ai', 'key' => 'provider', 'value' => 'openai']);
    PlatformSetting::query()->create(['group' => 'whatsapp', 'key' => 'access_token', 'value' => 'db-token']);

    app(PlatformSettings::class)->apply();

    expect(config('ai.provider'))->toBe('openai')
        ->and(config('services.whatsapp.access_token'))->toBe('db-token');
});

it('does not override config when a setting is blank (env wins)', function () {
    config(['services.zoom.account_id' => 'env-account']);
    PlatformSetting::query()->create(['group' => 'zoom', 'key' => 'account_id', 'value' => '']);

    app(PlatformSettings::class)->apply();

    expect(config('services.zoom.account_id'))->toBe('env-account');
});

it('ignores keys not on the whitelist', function () {
    Sanctum::actingAs($this->super);

    $this->putJson('/api/v1/admin/settings', ['settings' => ['ai' => ['app.key' => 'hijacked', 'not_a_real_field' => 'x']]])
        ->assertOk();

    expect(PlatformSetting::query()->where('key', 'app.key')->exists())->toBeFalse()
        ->and(PlatformSetting::query()->where('key', 'not_a_real_field')->exists())->toBeFalse();
});

it('audits which keys changed but never their values', function () {
    Sanctum::actingAs($this->super);

    $this->putJson('/api/v1/admin/settings', ['settings' => ['vapi' => ['api_key' => 'vapi-secret-xyz']]])->assertOk();

    $audit = AuditLog::query()->where('action', 'platform_settings.updated')->firstOrFail();
    expect($audit->metadata['keys'])->toContain('vapi.api_key')
        ->and(json_encode($audit->metadata))->not->toContain('vapi-secret-xyz');
});

it('denies a plain admin — super-admin only', function () {
    $admin = User::factory()->for($this->tenant)->create(['user_type' => 'staff']);
    $admin->assignRole('admin');
    Sanctum::actingAs($admin);

    $this->getJson('/api/v1/admin/settings')->assertForbidden();
    $this->putJson('/api/v1/admin/settings', ['settings' => ['ai' => ['provider' => 'openai']]])->assertForbidden();
});

it('denies a student', function () {
    $student = User::factory()->for($this->tenant)->create(['user_type' => 'student']);
    Sanctum::actingAs($student);

    $this->getJson('/api/v1/admin/settings')->assertForbidden();
});
