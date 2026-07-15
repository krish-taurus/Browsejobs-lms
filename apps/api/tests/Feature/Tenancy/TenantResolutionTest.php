<?php

declare(strict_types=1);

use App\Models\Tenant;
use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Illuminate\Support\Facades\Route;

beforeEach(function () {
    Route::middleware('tenant.domain')->get('/_test/tenant-by-domain', function () {
        return ['tenant_id' => app(TenantContext::class)->id()];
    });

    Route::middleware('tenant.user')->get('/_test/tenant-by-user', function () {
        return ['tenant_id' => app(TenantContext::class)->id()];
    });
});

it('resolves the tenant by request host on public routes', function () {
    $tenant = Tenant::factory()->domain('acme.test')->create();

    $this->get('http://acme.test/_test/tenant-by-domain')
        ->assertOk()
        ->assertJson(['tenant_id' => $tenant->id]);
});

it('404s an unknown host', function () {
    $this->get('http://nobody.test/_test/tenant-by-domain')->assertNotFound();
});

it('refuses a suspended tenant on domain resolution', function () {
    Tenant::factory()->domain('paused.test')->suspended()->create();

    $this->get('http://paused.test/_test/tenant-by-domain')->assertNotFound();
});

it('resolves the tenant from the authenticated user on portal routes', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->for($tenant)->create();

    $this->actingAs($user)
        ->get('/_test/tenant-by-user')
        ->assertOk()
        ->assertJson(['tenant_id' => $tenant->id]);
});

it('forbids a portal route when the user has no active tenant', function () {
    $tenant = Tenant::factory()->suspended()->create();
    $user = User::factory()->for($tenant)->create();

    $this->actingAs($user)
        ->get('/_test/tenant-by-user')
        ->assertForbidden();
});
