<?php

declare(strict_types=1);

use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
    config(['sanctum.stateful' => ['acme.test']]);

    $this->tenant = Tenant::factory()->domain('acme.test')->create();
});

/** Post as the first-party SPA so Sanctum treats the request as stateful. */
function passwordLoginPost(array $data)
{
    return test()
        ->withHeader('Origin', 'http://acme.test')
        ->postJson('http://acme.test/api/v1/auth/login', $data);
}

it('signs a student in with their issued email + password', function () {
    $student = User::factory()->for($this->tenant)->create([
        'email' => 'stu@acme.test', 'user_type' => 'student',
        'password' => 'BatchPass123',
    ]);
    $student->assignRole('student');

    passwordLoginPost(['identifier' => 'stu@acme.test', 'password' => 'BatchPass123'])
        ->assertOk()
        ->assertJsonPath('data.email', 'stu@acme.test');

    $this->assertAuthenticated();
});

it('signs a student in by phone + password when they have no email', function () {
    User::factory()->for($this->tenant)->create([
        'email' => null, 'phone' => '+919000077771', 'user_type' => 'student',
        'password' => 'BatchPass123',
    ]);

    passwordLoginPost(['identifier' => '+919000077771', 'password' => 'BatchPass123'])
        ->assertOk();

    $this->assertAuthenticated();
});

it('rejects a wrong password', function () {
    User::factory()->for($this->tenant)->create([
        'email' => 'stu@acme.test', 'user_type' => 'student',
        'password' => 'BatchPass123',
    ]);

    passwordLoginPost(['identifier' => 'stu@acme.test', 'password' => 'wrong-pass'])
        ->assertStatus(422);

    $this->assertGuest();
});

it('rejects password login for an OTP-only account that has no password', function () {
    User::factory()->for($this->tenant)->create([
        'email' => 'otp-only@acme.test', 'user_type' => 'student',
        'password' => null,
    ]);

    passwordLoginPost(['identifier' => 'otp-only@acme.test', 'password' => 'anything'])
        ->assertStatus(422);

    $this->assertGuest();
});
