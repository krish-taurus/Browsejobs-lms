<?php

declare(strict_types=1);

use App\Enums\BatchMemberStatus;
use App\Enums\BatchType;
use App\Models\Batch;
use App\Models\BatchMember;
use App\Models\Course;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "uses()" function to bind a different classes or traits.
|
*/

uses(
    TestCase::class,
    RefreshDatabase::class,
)->in('Feature');

// Never touch real object storage in tests. Course-completion now auto-issues a
// certificate (which renders to the s3 disk), so any completion path must have a
// faked disk; individual tests can re-fake to assert file contents.
uses()->beforeEach(fn () => Storage::fake('s3'))->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

/**
 * Run a block of assertions as if the given tenant were the active tenant.
 *
 * This is the reusable cross-tenant pattern: every feature's test suite drives
 * its "tenant A cannot see tenant B" assertions through here so scoping is
 * exercised the same way everywhere.
 *
 * @template TReturn
 *
 * @param  Closure(): TReturn  $callback
 * @return TReturn
 */
function withinTenant(Tenant $tenant, Closure $callback)
{
    return app(TenantContext::class)->run($tenant, $callback);
}

/**
 * A staff counselor in the tenant (role `counselor` grants `manage-leads`).
 * Requires RolePermissionSeeder to have run.
 */
function counselorIn(Tenant $tenant, ?string $name = null): User
{
    $user = User::factory()->for($tenant)->create([
        'user_type' => 'staff',
        'name' => $name ?? fake()->name(),
    ]);
    $user->assignRole('counselor');

    return $user;
}

/**
 * A paid batch with one Reserved student, for the payments suite.
 *
 * @return array{batch: Batch, student: User, member: BatchMember}
 */
function reservedMemberIn(Tenant $tenant, string $phone = '+91 90000 12345', ?string $email = null): array
{
    $email ??= fake()->unique()->safeEmail();
    $course = Course::factory()->for($tenant)->create();
    $batch = Batch::factory()->for($tenant)->create([
        'course_id' => $course->id,
        'type' => BatchType::Paid->value,
    ]);
    $student = User::factory()->for($tenant)->create([
        'user_type' => 'student', 'phone' => $phone, 'email' => $email,
    ]);
    $member = BatchMember::factory()->for($tenant)->create([
        'batch_id' => $batch->id,
        'user_id' => $student->id,
        'status' => BatchMemberStatus::Reserved->value,
    ]);

    return compact('batch', 'student', 'member');
}

/**
 * A bootcamp batch linked to a paid batch with one Enrolled attendee — for the
 * P2.5 conversion suite.
 *
 * @return array{bootcamp: Batch, paid: Batch, student: User, member: BatchMember}
 */
function bootcampConversionSetup(Tenant $tenant, string $phone = '+91 90000 33333', ?string $email = 'attendee@example.com'): array
{
    $course = Course::factory()->for($tenant)->create();
    $bootcamp = Batch::factory()->for($tenant)->create([
        'course_id' => $course->id, 'type' => BatchType::Bootcamp->value,
    ]);
    $paid = Batch::factory()->for($tenant)->create([
        'course_id' => $course->id, 'type' => BatchType::Paid->value,
        'linked_source_batch_id' => $bootcamp->id,
    ]);
    $student = User::factory()->for($tenant)->create([
        'user_type' => 'student', 'phone' => $phone, 'email' => $email,
    ]);
    $member = BatchMember::factory()->for($tenant)->create([
        'batch_id' => $bootcamp->id, 'user_id' => $student->id,
        'status' => BatchMemberStatus::Enrolled->value,
    ]);

    return compact('bootcamp', 'paid', 'student', 'member');
}
