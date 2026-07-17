<?php

declare(strict_types=1);

use App\Enums\BatchType;
use App\Models\Batch;
use App\Models\BatchMember;
use App\Models\Course;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
    $this->tenant = Tenant::factory()->create();
    $this->admin = User::factory()->for($this->tenant)->create(['user_type' => 'staff']);
    $this->admin->assignRole('admin');

    [$this->batchA, $this->batchB] = withinTenant($this->tenant, function () {
        $course = Course::query()->create(['code' => 'DA', 'name' => 'Data Analytics', 'slug' => 'da']);

        return [
            $course->batches()->create(['number' => 'DA-202608-01', 'type' => BatchType::Paid->value]),
            $course->batches()->create(['number' => 'DA-202609-01', 'type' => BatchType::Paid->value, 'capacity' => 1]),
        ];
    });

    $this->asha = User::factory()->for($this->tenant)->create(['user_type' => 'student', 'name' => 'Asha Rao', 'phone' => '+919000000001']);
    $this->ben = User::factory()->for($this->tenant)->create(['user_type' => 'student', 'name' => 'Ben Kumar', 'phone' => '+919000000002']);

    withinTenant($this->tenant, function () {
        BatchMember::query()->create(['batch_id' => $this->batchA->id, 'user_id' => $this->asha->id, 'status' => 'enrolled']);
    });
});

it('lists all students, batch-first no longer required', function () {
    Sanctum::actingAs($this->admin);

    $this->getJson('/api/v1/admin/students')->assertOk()->assertJsonCount(2, 'data');
});

it('shows a student their current batches with member ids', function () {
    Sanctum::actingAs($this->admin);

    $body = $this->getJson('/api/v1/admin/students')->assertOk();
    $asha = collect($body->json('data'))->firstWhere('name', 'Asha Rao');

    expect($asha['batches'])->toHaveCount(1)
        ->and($asha['batches'][0]['number'])->toBe('DA-202608-01')
        ->and($asha['batches'][0]['status'])->toBe('enrolled')
        ->and($asha['batches'][0])->toHaveKey('member_id');
});

it('filters by batch', function () {
    Sanctum::actingAs($this->admin);

    // Only Asha is in batch A.
    $body = $this->getJson("/api/v1/admin/students?batch_id={$this->batchA->id}")->assertOk()->assertJsonCount(1, 'data');
    expect($body->json('data.0.name'))->toBe('Asha Rao');

    // Nobody is in batch B yet.
    $this->getJson("/api/v1/admin/students?batch_id={$this->batchB->id}")->assertOk()->assertJsonCount(0, 'data');
});

it('searches by name or phone', function () {
    Sanctum::actingAs($this->admin);

    $this->getJson('/api/v1/admin/students?q=Ben')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.name', 'Ben Kumar');
    $this->getJson('/api/v1/admin/students?q=9000000001')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.name', 'Asha Rao');
});

it('adds a student to an additional batch, keeping the current one', function () {
    Sanctum::actingAs($this->admin);

    $this->postJson("/api/v1/admin/students/{$this->asha->id}/enrollments", ['batch_id' => $this->batchB->id])
        ->assertCreated();

    // Asha is now in BOTH batches.
    $memberships = BatchMember::withoutGlobalScopes()->where('user_id', $this->asha->id)->pluck('batch_id');
    expect($memberships)->toContain($this->batchA->id)->toContain($this->batchB->id);
});

it('rejects adding a student to a batch they are already in', function () {
    Sanctum::actingAs($this->admin);

    $this->postJson("/api/v1/admin/students/{$this->asha->id}/enrollments", ['batch_id' => $this->batchA->id])
        ->assertStatus(422);
});

it('enforces target batch capacity', function () {
    // batchB has capacity 1; fill it, then Asha should be rejected.
    withinTenant($this->tenant, fn () => BatchMember::query()->create(['batch_id' => $this->batchB->id, 'user_id' => $this->ben->id, 'status' => 'enrolled']));

    Sanctum::actingAs($this->admin);
    $this->postJson("/api/v1/admin/students/{$this->asha->id}/enrollments", ['batch_id' => $this->batchB->id])
        ->assertStatus(422);
});

it('moving a student drops them from the source and shows only the target', function () {
    Sanctum::actingAs($this->admin);
    $member = BatchMember::withoutGlobalScopes()->where('user_id', $this->asha->id)->where('batch_id', $this->batchA->id)->firstOrFail();

    // Reuse the existing transfer route.
    $this->postJson("/api/v1/admin/members/{$member->id}/transfer", ['target_batch_id' => $this->batchB->id, 'reason' => 'Batch clash'])
        ->assertOk();

    // The candidates view now shows Asha in B only — the transferred source is history.
    $body = $this->getJson('/api/v1/admin/students')->assertOk();
    $asha = collect($body->json('data'))->firstWhere('name', 'Asha Rao');
    expect($asha['batches'])->toHaveCount(1)->and($asha['batches'][0]['number'])->toBe('DA-202609-01');
});

it('excludes staff from the candidates list', function () {
    Sanctum::actingAs($this->admin);

    // The admin themselves is user_type=staff and must not appear.
    $names = collect($this->getJson('/api/v1/admin/students')->json('data'))->pluck('name');
    expect($names)->not->toContain($this->admin->name);
});

it('denies a user without manage-rosters', function () {
    $support = User::factory()->for($this->tenant)->create(['user_type' => 'staff']);
    $support->assignRole('support-agent');
    Sanctum::actingAs($support);

    $this->getJson('/api/v1/admin/students')->assertForbidden();
});

it('404s enrolling into another tenant batch', function () {
    $other = Tenant::factory()->create();
    $theirBatch = withinTenant($other, function () use ($other) {
        $course = Course::query()->create(['code' => 'XX', 'name' => 'X', 'slug' => 'x', 'tenant_id' => $other->id]);

        return $course->batches()->create(['number' => 'XX-1', 'type' => BatchType::Paid->value, 'tenant_id' => $other->id]);
    });

    Sanctum::actingAs($this->admin);
    $this->postJson("/api/v1/admin/students/{$this->asha->id}/enrollments", ['batch_id' => $theirBatch->id])
        ->assertNotFound();
});

it('does not list another tenant students', function () {
    $other = Tenant::factory()->create();
    User::factory()->for($other)->create(['user_type' => 'student', 'name' => 'Outsider']);

    Sanctum::actingAs($this->admin);
    $names = collect($this->getJson('/api/v1/admin/students')->json('data'))->pluck('name');
    expect($names)->not->toContain('Outsider');
});
