<?php

declare(strict_types=1);

use App\Models\SalaryBenchmark;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Laravel\Sanctum\Sanctum;

use function Pest\Laravel\getJson;
use function Pest\Laravel\postJson;

beforeEach(function () {
    $this->tenant = Tenant::factory()->create();
    $this->seed(RolePermissionSeeder::class);
});

function benchmarkStaff(Tenant $tenant): User
{
    $user = User::factory()->for($tenant)->create(['user_type' => 'staff']);
    $user->assignRole('placement-officer');

    return $user;
}

it('stores a benchmark in paise from LPA input', function () {
    Sanctum::actingAs(benchmarkStaff($this->tenant));

    postJson('/api/v1/admin/salary-benchmarks', [
        'role_title' => 'Data Engineer', 'city' => 'Bengaluru', 'experience_band' => 'fresher',
        'p25_lpa' => 6, 'p50_lpa' => 8, 'p75_lpa' => 11,
    ])->assertCreated();

    $row = SalaryBenchmark::withoutGlobalScopes()->sole();
    expect($row->p50_ctc_paise)->toBe(8_00_000 * 100); // 8 LPA in paise
});

it('rejects an invalid experience band', function () {
    Sanctum::actingAs(benchmarkStaff($this->tenant));
    postJson('/api/v1/admin/salary-benchmarks', [
        'role_title' => 'DE', 'city' => 'BLR', 'experience_band' => 'wizard',
        'p25_lpa' => 6, 'p50_lpa' => 8, 'p75_lpa' => 11,
    ])->assertStatus(422);
});

it('lets a student read benchmarks by role in LPA', function () {
    SalaryBenchmark::factory()->for($this->tenant)->create(['role_title' => 'Data Engineer']);
    Sanctum::actingAs(User::factory()->for($this->tenant)->create(['user_type' => 'student']));

    getJson('/api/v1/me/salary-benchmarks?role=Data')->assertOk()
        ->assertJsonPath('data.0.role_title', 'Data Engineer')
        ->assertJsonPath('data.0.p50_lpa', 8);
});

it('gates benchmark management behind the placement ability', function () {
    Sanctum::actingAs(User::factory()->for($this->tenant)->create(['user_type' => 'student']));
    postJson('/api/v1/admin/salary-benchmarks', [])->assertForbidden();
});

it('does not leak benchmarks across tenants', function () {
    $other = Tenant::factory()->create();
    SalaryBenchmark::factory()->for($other)->create(['role_title' => 'Secret Role']);

    Sanctum::actingAs(User::factory()->for($this->tenant)->create(['user_type' => 'student']));
    getJson('/api/v1/me/salary-benchmarks')->assertOk()->assertJsonPath('data', []);
});
