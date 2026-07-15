<?php

declare(strict_types=1);

use App\Models\StaffProfile;
use App\Models\SupportTeam;
use App\Models\Tenant;
use App\Models\User;

it('links a staff profile and support-team memberships to a user', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->for($tenant)->create(['user_type' => 'staff']);

    withinTenant($tenant, function () use ($user) {
        StaffProfile::query()->create([
            'user_id' => $user->id,
            'title' => 'Senior Trainer',
            'description' => 'Leads the data-engineering cohort.',
        ]);

        $billing = SupportTeam::query()->create([
            'name' => 'Accounts', 'slug' => 'accounts', 'category' => 'payments',
        ]);
        $training = SupportTeam::query()->create([
            'name' => 'Training', 'slug' => 'training', 'category' => 'training',
        ]);

        $user->supportTeams()->sync([$billing->id, $training->id]);
    });

    $user->refresh();

    expect($user->staffProfile->title)->toBe('Senior Trainer')
        ->and($user->supportTeams->pluck('category')->all())
        ->toEqualCanonicalizing(['payments', 'training']);
});

it('scopes staff profiles and support teams to their tenant', function () {
    $tenantA = Tenant::factory()->create();
    $tenantB = Tenant::factory()->create();

    $userB = User::factory()->for($tenantB)->create();
    withinTenant($tenantB, fn () => StaffProfile::query()->create([
        'user_id' => $userB->id, 'title' => 'B-only',
    ]));

    withinTenant($tenantA, function () {
        expect(StaffProfile::query()->count())->toBe(0)
            ->and(SupportTeam::query()->count())->toBe(0);
    });
});
