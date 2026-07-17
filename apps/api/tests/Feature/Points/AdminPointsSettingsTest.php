<?php

declare(strict_types=1);

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

it('returns default weights and persists updates', function () {
    Sanctum::actingAs($this->admin);

    $this->getJson('/api/v1/admin/points-settings')
        ->assertOk()
        ->assertJsonPath('data.quiz_points', 30)
        ->assertJsonPath('data.daily_cap', 150);

    $this->putJson('/api/v1/admin/points-settings', ['quiz_points' => 45, 'daily_cap' => 200])
        ->assertOk()
        ->assertJsonPath('data.quiz_points', 45)
        ->assertJsonPath('data.daily_cap', 200);

    $this->getJson('/api/v1/admin/points-settings')->assertJsonPath('data.quiz_points', 45);
});

it('rejects invalid weights', function () {
    Sanctum::actingAs($this->admin);

    $this->putJson('/api/v1/admin/points-settings', ['attendance_min_pct' => 250])->assertStatus(422);
});

it('forbids students', function () {
    $student = User::factory()->for($this->tenant)->create(['user_type' => 'student']);
    $student->assignRole('student');
    Sanctum::actingAs($student);

    $this->getJson('/api/v1/admin/points-settings')->assertForbidden();
});
