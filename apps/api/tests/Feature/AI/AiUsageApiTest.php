<?php

declare(strict_types=1);

use App\Models\AiEvent;
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

it('aggregates AI usage by purpose scoped to the tenant', function () {
    AiEvent::factory()->for($this->tenant)->create(['purpose' => 'tutor', 'total_tokens' => 100, 'cost_micros' => 50_000]);
    AiEvent::factory()->for($this->tenant)->create(['purpose' => 'tutor', 'total_tokens' => 50, 'cost_micros' => 25_000]);
    AiEvent::factory()->for(Tenant::factory()->create())->create(['purpose' => 'tutor', 'total_tokens' => 999]);

    Sanctum::actingAs($this->admin);

    $this->getJson('/api/v1/admin/ai-usage')
        ->assertOk()
        ->assertJsonPath('data.today.tokens', 150)
        ->assertJsonPath('data.today.budget_per_student', 200000)
        ->assertJsonPath('data.by_purpose.0.purpose', 'tutor')
        ->assertJsonPath('data.by_purpose.0.tokens', 150);
});

it('denies a staff user without manage-monetization', function () {
    $mentor = User::factory()->for($this->tenant)->create(['user_type' => 'staff']);
    $mentor->assignRole('mentor');

    Sanctum::actingAs($mentor);

    $this->getJson('/api/v1/admin/ai-usage')->assertForbidden();
});
