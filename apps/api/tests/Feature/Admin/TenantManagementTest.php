<?php

declare(strict_types=1);

use App\Models\AuditLog;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
    $this->tenant = Tenant::factory()->create(['slug' => 'acme']);
    $this->super = User::factory()->for($this->tenant)->create(['user_type' => 'staff']);
    $this->super->assignRole('super-admin');
});

it('provisions a new tenant with default branding', function () {
    Sanctum::actingAs($this->super);

    $this->postJson('/api/v1/admin/tenants', ['name' => 'Zeta Institute', 'domain' => 'zeta.test'])
        ->assertCreated()->assertJsonPath('data.slug', 'zeta-institute');

    $tenant = Tenant::query()->where('slug', 'zeta-institute')->sole();
    expect($tenant->status)->toBe('active')
        ->and($tenant->branding['colors']['trust_blue'])->toBe('#1B6DF0')
        ->and($tenant->domain)->toBe('zeta.test');
    expect(AuditLog::query()->where('action', 'tenant.provisioned')->exists())->toBeTrue();
});

it('rejects a duplicate slug', function () {
    Sanctum::actingAs($this->super);
    $this->postJson('/api/v1/admin/tenants', ['name' => 'Acme', 'slug' => 'acme'])->assertStatus(422);
});

it('updates whitelabel branding, merging a partial change', function () {
    $this->tenant->update(['branding' => ['colors' => ['trust_blue' => '#1B6DF0', 'paper' => '#F6F9FE'], 'fonts' => ['display' => 'Sora']]]);
    Sanctum::actingAs($this->super);

    $this->putJson("/api/v1/admin/tenants/{$this->tenant->id}/branding", [
        'branding' => ['colors' => ['trust_blue' => '#7C3AED'], 'logo_url' => 'https://cdn.test/logo.svg'],
    ])->assertOk();

    $branding = $this->tenant->fresh()->branding;
    expect($branding['colors']['trust_blue'])->toBe('#7C3AED')   // changed
        ->and($branding['colors']['paper'])->toBe('#F6F9FE')     // preserved
        ->and($branding['logo_url'])->toBe('https://cdn.test/logo.svg');
});

it('rejects an invalid hex colour', function () {
    Sanctum::actingAs($this->super);
    $this->putJson("/api/v1/admin/tenants/{$this->tenant->id}/branding", [
        'branding' => ['colors' => ['trust_blue' => 'purple']],
    ])->assertStatus(422);
});

it('toggles feature flags and the model reads them', function () {
    Sanctum::actingAs($this->super);

    $this->putJson("/api/v1/admin/tenants/{$this->tenant->id}/flags", [
        'feature_flags' => ['leaderboards' => false],
    ])->assertOk();

    expect($this->tenant->fresh()->feature('leaderboards'))->toBeFalse()
        ->and($this->tenant->fresh()->feature('mock_interviewer'))->toBeTrue(); // unset → default on
});

it('denies a non-super-admin', function () {
    $trainer = User::factory()->for($this->tenant)->create(['user_type' => 'staff']);
    $trainer->assignRole('trainer');
    Sanctum::actingAs($trainer);

    $this->getJson('/api/v1/admin/tenants')->assertForbidden();
    $this->postJson('/api/v1/admin/tenants', ['name' => 'X'])->assertForbidden();
});

it('requires authentication', function () {
    $this->getJson('/api/v1/admin/tenants')->assertUnauthorized();
});
