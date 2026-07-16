<?php

declare(strict_types=1);

use App\Models\ConversionNudge;
use App\Models\FeePlan;
use App\Models\Tenant;

beforeEach(function () {
    $this->tenantA = Tenant::factory()->create();
    $this->tenantB = Tenant::factory()->create();
});

it('scopes conversion_nudges to their tenant', function () {
    $planA = FeePlan::factory()->for($this->tenantA)->create();
    $planB = FeePlan::factory()->for($this->tenantB)->create();
    $nudgeA = ConversionNudge::factory()->for($this->tenantA)->create(['fee_plan_id' => $planA->id]);
    $nudgeB = ConversionNudge::factory()->for($this->tenantB)->create(['fee_plan_id' => $planB->id]);

    withinTenant($this->tenantA, fn () => expect(ConversionNudge::query()->find($nudgeB->id))->toBeNull());
    withinTenant($this->tenantB, fn () => expect(ConversionNudge::query()->find($nudgeA->id))->toBeNull());
});
