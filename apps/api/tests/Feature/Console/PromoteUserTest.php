<?php

declare(strict_types=1);

use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;

use function Pest\Laravel\artisan;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
    $this->tenant = Tenant::factory()->create();
});

it('grants super-admin to a user by email', function () {
    $user = User::factory()->for($this->tenant)->create(['email' => 'owner@example.com', 'user_type' => 'staff']);

    artisan('user:promote', ['email' => 'owner@example.com'])->assertExitCode(0);

    expect($user->fresh()->hasRole('super-admin'))->toBeTrue();
});

it('grants an arbitrary role via --role', function () {
    $user = User::factory()->for($this->tenant)->create(['email' => 'coach@example.com', 'user_type' => 'staff']);

    artisan('user:promote', ['email' => 'coach@example.com', '--role' => 'trainer'])->assertExitCode(0);

    expect($user->fresh()->hasRole('trainer'))->toBeTrue();
});

it('fails on an unknown email', function () {
    artisan('user:promote', ['email' => 'nobody@example.com'])->assertExitCode(1);
});

it('fails on an unknown role', function () {
    User::factory()->for($this->tenant)->create(['email' => 'owner@example.com']);

    artisan('user:promote', ['email' => 'owner@example.com', '--role' => 'wizard'])->assertExitCode(1);
});

it('is idempotent when the user already has the role', function () {
    $user = User::factory()->for($this->tenant)->create(['email' => 'owner@example.com', 'user_type' => 'staff']);
    $user->assignRole('super-admin');

    artisan('user:promote', ['email' => 'owner@example.com'])->assertExitCode(0);

    expect($user->fresh()->roles()->where('slug', 'super-admin')->count())->toBe(1);
});
