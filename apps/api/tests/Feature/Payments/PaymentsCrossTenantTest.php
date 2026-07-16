<?php

declare(strict_types=1);

use App\Models\FeePlan;
use App\Models\Instalment;
use App\Models\LedgerEntry;
use App\Models\Payment;
use App\Models\Receipt;
use App\Models\Tenant;
use App\Models\WebhookLog;

beforeEach(function () {
    $this->tenantA = Tenant::factory()->create();
    $this->tenantB = Tenant::factory()->create();
});

it('scopes every payments model to its tenant', function () {
    $planA = FeePlan::factory()->for($this->tenantA)->create();
    $planB = FeePlan::factory()->for($this->tenantB)->create();

    $instA = Instalment::factory()->for($this->tenantA)->create(['fee_plan_id' => $planA->id]);
    $instB = Instalment::factory()->for($this->tenantB)->create(['fee_plan_id' => $planB->id]);

    $payA = Payment::factory()->for($this->tenantA)->create(['fee_plan_id' => $planA->id, 'instalment_id' => $instA->id]);
    $payB = Payment::factory()->for($this->tenantB)->create(['fee_plan_id' => $planB->id, 'instalment_id' => $instB->id]);

    $recA = Receipt::factory()->for($this->tenantA)->create(['fee_plan_id' => $planA->id, 'payment_id' => $payA->id]);
    $recB = Receipt::factory()->for($this->tenantB)->create(['fee_plan_id' => $planB->id, 'payment_id' => $payB->id]);

    $ledA = LedgerEntry::factory()->for($this->tenantA)->create(['fee_plan_id' => $planA->id]);
    $ledB = LedgerEntry::factory()->for($this->tenantB)->create(['fee_plan_id' => $planB->id]);

    $whA = WebhookLog::factory()->for($this->tenantA)->create();
    $whB = WebhookLog::factory()->for($this->tenantB)->create();

    withinTenant($this->tenantA, function () use ($planB, $instB, $payB, $recB, $ledB, $whB) {
        expect(FeePlan::query()->find($planB->id))->toBeNull();
        expect(Instalment::query()->find($instB->id))->toBeNull();
        expect(Payment::query()->find($payB->id))->toBeNull();
        expect(Receipt::query()->find($recB->id))->toBeNull();
        expect(LedgerEntry::query()->find($ledB->id))->toBeNull();
        expect(WebhookLog::query()->find($whB->id))->toBeNull();
    });

    withinTenant($this->tenantB, function () use ($planA, $instA, $payA, $recA, $ledA, $whA) {
        expect(FeePlan::query()->find($planA->id))->toBeNull();
        expect(Instalment::query()->find($instA->id))->toBeNull();
        expect(Payment::query()->find($payA->id))->toBeNull();
        expect(Receipt::query()->find($recA->id))->toBeNull();
        expect(LedgerEntry::query()->find($ledA->id))->toBeNull();
        expect(WebhookLog::query()->find($whA->id))->toBeNull();
    });
});
