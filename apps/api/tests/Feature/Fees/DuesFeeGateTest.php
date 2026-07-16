<?php

declare(strict_types=1);

use App\Models\AccessBlock;
use App\Models\Batch;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Fees\DuesFeeGate;

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
