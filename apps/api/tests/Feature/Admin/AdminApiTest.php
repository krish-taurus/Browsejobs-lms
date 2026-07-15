<?php

declare(strict_types=1);

use App\Models\Batch;
use App\Models\BatchMember;
use App\Models\Course;
use App\Models\Module;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);

    $this->tenant = Tenant::factory()->create();
    $this->admin = User::factory()->for($this->tenant)->create(['user_type' => 'staff']);
    $this->admin->assignRole('admin');

    $this->course = withinTenant($this->tenant, fn () => Course::query()->create([
        'code' => 'DE', 'name' => 'Data Engineering', 'slug' => 'data-engineering',
    ]));
});

function actingAsAdmin(): void
{
    Sanctum::actingAs(test()->admin);
}

it('lists and shows curriculum for the admin tenant', function () {
    actingAsAdmin();

    $this->getJson('/api/v1/admin/courses')
        ->assertOk()
        ->assertJsonPath('data.0.code', 'DE');

    $this->getJson("/api/v1/admin/courses/{$this->course->id}")
        ->assertOk()
        ->assertJsonPath('data.name', 'Data Engineering');
});

it('creates module, topic, and lesson through the admin API', function () {
    actingAsAdmin();

    $module = $this->postJson('/api/v1/admin/modules', [
        'course_id' => $this->course->id, 'name' => 'Foundations',
    ])->assertCreated()->json('data');

    $topic = $this->postJson('/api/v1/admin/topics', [
        'module_id' => $module['id'], 'name' => 'SQL Basics',
    ])->assertCreated()->json('data');

    $this->postJson('/api/v1/admin/lessons', [
        'topic_id' => $topic['id'], 'title' => 'Joins deep-dive', 'type' => 'live_class',
    ])->assertCreated();

    $this->patchJson("/api/v1/admin/modules/{$module['id']}", ['name' => 'Foundations v2'])
        ->assertOk()
        ->assertJsonPath('data.name', 'Foundations v2');
});

it('creates a batch with an auto-generated number and shows its roster', function () {
    actingAsAdmin();

    $batch = $this->postJson('/api/v1/admin/batches', [
        'course_id' => $this->course->id, 'type' => 'bootcamp', 'capacity' => 30,
    ])->assertCreated()->json('data');

    expect($batch['number'])->toStartWith('DE-');

    $this->getJson("/api/v1/admin/batches/{$batch['id']}")
        ->assertOk()
        ->assertJsonPath('data.number', $batch['number']);
});

it('runs the roster flow over HTTP: add, import, transfer, remove', function () {
    actingAsAdmin();

    $batchA = withinTenant($this->tenant, fn () => Batch::query()->create([
        'course_id' => $this->course->id, 'number' => 'DE-A', 'type' => 'bootcamp',
    ]));
    $batchB = withinTenant($this->tenant, fn () => Batch::query()->create([
        'course_id' => $this->course->id, 'number' => 'DE-B', 'type' => 'paid',
    ]));

    $member = $this->postJson("/api/v1/admin/batches/{$batchA->id}/members", [
        'name' => 'Asha Rao', 'phone' => '+919000000042',
    ])->assertCreated()->json('data');

    $this->postJson("/api/v1/admin/batches/{$batchA->id}/import", [
        'csv' => "name,phone,email\nRavi,+919000000043,\nBad Row,,",
    ])->assertOk()->assertJsonPath('data.imported', 1)->assertJsonPath('data.skipped.0.row', 2);

    $this->postJson("/api/v1/admin/members/{$member['id']}/transfer", [
        'target_batch_id' => $batchB->id, 'reason' => 'Upgraded to paid',
    ])->assertOk()->assertJsonPath('data.batch_id', $batchB->id);

    $imported = BatchMember::query()->where('batch_id', $batchA->id)->latest('id')->first();
    $this->postJson("/api/v1/admin/members/{$imported->id}/remove", ['reason' => 'Withdrew'])
        ->assertOk()
        ->assertJsonPath('data.status', 'dropped');
});

it('forbids students from every admin endpoint', function () {
    $student = User::factory()->for($this->tenant)->create(['user_type' => 'student']);
    $student->assignRole('student');
    Sanctum::actingAs($student);

    $this->getJson('/api/v1/admin/courses')->assertForbidden();
    $this->postJson('/api/v1/admin/batches', [])->assertForbidden();
});

it('denies cross-tenant access: another tenants curriculum 404s', function () {
    actingAsAdmin();

    $otherTenant = Tenant::factory()->create();
    $foreignCourse = withinTenant($otherTenant, fn () => Course::query()->create([
        'code' => 'XX', 'name' => 'Foreign', 'slug' => 'foreign',
    ]));
    $foreignModule = withinTenant($otherTenant, fn () => Module::query()->create([
        'course_id' => $foreignCourse->id, 'name' => 'Their module',
    ]));

    $this->getJson("/api/v1/admin/courses/{$foreignCourse->id}")->assertNotFound();
    $this->patchJson("/api/v1/admin/modules/{$foreignModule->id}", ['name' => 'hijack'])->assertNotFound();

    expect($foreignModule->fresh()->name)->toBe('Their module');
});
