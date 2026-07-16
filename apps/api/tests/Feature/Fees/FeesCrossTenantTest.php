<?php

declare(strict_types=1);

use App\Models\AccessBlock;
use App\Models\FeePlan;
use App\Models\FeeReminder;
use App\Models\Instalment;
use App\Models\Tenant;
use App\Models\User;

beforeEach(function () {
    $this->tenantA = Tenant::factory()->create();
    $this->tenantB = Tenant::factory()->create();
});

it('scopes access_blocks and fee_reminders to their tenant', function () {
    $userA = User::factory()->for($this->tenantA)->create();
    $userB = User::factory()->for($this->tenantB)->create();

    $blockA = AccessBlock::factory()->for($this->tenantA)->create(['user_id' => $userA->id]);
    $blockB = AccessBlock::factory()->for($this->tenantB)->create(['user_id' => $userB->id]);

    $planA = FeePlan::factory()->for($this->tenantA)->create();
    $planB = FeePlan::factory()->for($this->tenantB)->create();
    $instA = Instalment::factory()->for($this->tenantA)->create(['fee_plan_id' => $planA->id]);
    $instB = Instalment::factory()->for($this->tenantB)->create(['fee_plan_id' => $planB->id]);
    $reminderA = FeeReminder::factory()->for($this->tenantA)->create(['instalment_id' => $instA->id]);
    $reminderB = FeeReminder::factory()->for($this->tenantB)->create(['instalment_id' => $instB->id]);

    withinTenant($this->tenantA, function () use ($blockB, $reminderB) {
        expect(AccessBlock::query()->find($blockB->id))->toBeNull();
        expect(FeeReminder::query()->find($reminderB->id))->toBeNull();
    });

    withinTenant($this->tenantB, function () use ($blockA, $reminderA) {
        expect(AccessBlock::query()->find($blockA->id))->toBeNull();
        expect(FeeReminder::query()->find($reminderA->id))->toBeNull();
    });
});
