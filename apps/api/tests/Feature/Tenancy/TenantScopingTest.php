<?php

declare(strict_types=1);

use App\Models\Tenant;
use App\Models\User;
use App\Support\Tenancy\TenantContext;

it('hides other tenants rows from reads when a tenant is active', function () {
    $tenantA = Tenant::factory()->create();
    $tenantB = Tenant::factory()->create();

    $userA = User::factory()->for($tenantA)->create();
    $userB = User::factory()->for($tenantB)->create();

    withinTenant($tenantA, function () use ($userA, $userB) {
        expect(User::query()->pluck('id')->all())
            ->toContain($userA->id)
            ->not->toContain($userB->id);

        expect(User::find($userB->id))->toBeNull();
    });
});

it('denies cross-tenant writes: another tenants row cannot be loaded to update or delete', function () {
    $tenantA = Tenant::factory()->create();
    $tenantB = Tenant::factory()->create();

    $userB = User::factory()->for($tenantB)->create();

    withinTenant($tenantA, function () use ($userB) {
        // The scope hides tenant B's row, so it can never be fetched for a write.
        expect(User::find($userB->id))->toBeNull();

        $affected = User::query()->whereKey($userB->id)->update(['name' => 'hijacked']);
        expect($affected)->toBe(0);

        $deleted = User::query()->whereKey($userB->id)->delete();
        expect($deleted)->toBe(0);
    });

    // Tenant B's row is untouched.
    expect($userB->fresh()->name)->not->toBe('hijacked');
});

it('auto-stamps tenant_id on create from the active tenant', function () {
    $tenant = Tenant::factory()->create();

    $user = withinTenant($tenant, fn () => User::factory()->create(['tenant_id' => null]));

    expect($user->tenant_id)->toBe($tenant->id);
});

it('does not scope queries when no tenant is active (console / super-admin)', function () {
    $userA = User::factory()->create();
    $userB = User::factory()->create();

    app(TenantContext::class)->forget();

    expect(User::query()->pluck('id')->all())
        ->toContain($userA->id)
        ->toContain($userB->id);
});
