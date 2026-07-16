<?php

declare(strict_types=1);

use App\Models\Lead;
use App\Models\LeadStage;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
    $this->tenant = Tenant::factory()->create();
    $this->admin = User::factory()->for($this->tenant)->create(['user_type' => 'staff']);
    $this->admin->assignRole('admin');

    $this->stages = withinTenant($this->tenant, function () {
        return collect([
            ['slug' => 'lead', 'position' => 1],
            ['slug' => 'masterclass-registered', 'position' => 2],
            ['slug' => 'bootcamp', 'position' => 4],
            ['slug' => 'reserved', 'position' => 5],
            ['slug' => 'enrolled', 'position' => 6],
        ])->mapWithKeys(fn ($s) => [$s['slug'] => LeadStage::query()->create([
            'name' => $s['slug'], 'slug' => $s['slug'], 'position' => $s['position'],
        ])]);
    });
});

it('aggregates the funnel by stage and by campaign', function () {
    Lead::factory()->for($this->tenant)->count(3)->create(['lead_stage_id' => $this->stages['lead']->id, 'utm_campaign' => 'aug-de']);
    Lead::factory()->for($this->tenant)->count(2)->create(['lead_stage_id' => $this->stages['enrolled']->id, 'utm_campaign' => 'aug-de']);

    Sanctum::actingAs($this->admin);

    $res = $this->getJson('/api/v1/admin/funnel')->assertOk();

    $res->assertJsonPath('data.total_leads', 5);
    $funnel = collect($res->json('data.funnel'))->keyBy('key');
    expect($funnel['lead']['count'])->toBe(5)
        ->and($funnel['paid']['count'])->toBe(2);
    $res->assertJsonPath('data.campaigns.0.campaign', 'aug-de')
        ->assertJsonPath('data.campaigns.0.leads', 5)
        ->assertJsonPath('data.campaigns.0.paid', 2);
});

it('is gated by manage-leads', function () {
    $mentor = User::factory()->for($this->tenant)->create(['user_type' => 'staff']);
    $mentor->assignRole('mentor');
    Sanctum::actingAs($mentor);

    $this->getJson('/api/v1/admin/funnel')->assertForbidden();
});

it('scopes the funnel to the tenant', function () {
    $other = Tenant::factory()->create();
    Lead::factory()->for($other)->count(4)->create();

    Sanctum::actingAs($this->admin);

    $this->getJson('/api/v1/admin/funnel')->assertOk()->assertJsonPath('data.total_leads', 0);
});
