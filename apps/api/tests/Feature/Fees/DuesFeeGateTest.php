<?php

declare(strict_types=1);

use App\Models\AccessBlock;
use App\Models\Batch;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Fees\DuesFeeGate;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->tenant = Tenant::factory()->create();
    $this->student = User::factory()->for($this->tenant)->create(['user_type' => 'student']);
    $this->batch = Batch::factory()->for($this->tenant)->create();
    $this->gate = app(DuesFeeGate::class);
});

it('allows a student with no active block', function () {
    expect($this->gate->allowsLiveAccess($this->student, $this->batch))->toBeTrue();
});

it('denies a student with an active soft block', function () {
    AccessBlock::factory()->for($this->tenant)->create(['user_id' => $this->student->id]);

    expect($this->gate->allowsLiveAccess($this->student, $this->batch))->toBeFalse();
});

it('denies a student with an active hard block', function () {
    AccessBlock::factory()->for($this->tenant)->hard()->create(['user_id' => $this->student->id]);

    expect($this->gate->allowsLiveAccess($this->student, $this->batch))->toBeFalse();
});

it('allows again once the block is lifted', function () {
    AccessBlock::factory()->for($this->tenant)->lifted()->create(['user_id' => $this->student->id]);

    expect($this->gate->allowsLiveAccess($this->student, $this->batch))->toBeTrue();
});

it('does not leak a block across tenants', function () {
    $otherTenant = Tenant::factory()->create();
    $otherStudent = User::factory()->for($otherTenant)->create(['user_type' => 'student']);
    AccessBlock::factory()->for($otherTenant)->create(['user_id' => $otherStudent->id]);

    // A block on a different tenant's student must not block ours.
    expect($this->gate->allowsLiveAccess($this->student, $this->batch))->toBeTrue();
});

it('hard-locks mocks, tutor, and mentor booking server-side', function () {
    AccessBlock::factory()->for($this->tenant)->hard()->create(['user_id' => $this->student->id]);
    Sanctum::actingAs($this->student);

    $this->getJson('/api/v1/me/mocks')->assertForbidden();
    $this->postJson('/api/v1/me/mocks', ['room' => true])->assertForbidden();
    $this->getJson('/api/v1/me/tutor')->assertForbidden();
    $this->postJson('/api/v1/me/mentor-sessions', ['mentor_id' => 1, 'starts_at' => now()->addDay()->toIso8601String()])->assertForbidden();
});

it('leaves mocks and tutor open under a soft block', function () {
    // Soft blocks only lock live classes + recordings; practice keeps working
    // so a student inside the grace window is nudged, not paralysed.
    AccessBlock::factory()->for($this->tenant)->create(['user_id' => $this->student->id]);
    Sanctum::actingAs($this->student);

    $this->getJson('/api/v1/me/mocks')->assertOk();
    $this->getJson('/api/v1/me/tutor')->assertOk();
});
