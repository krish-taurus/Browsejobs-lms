<?php

declare(strict_types=1);

use App\Models\ContactTimelineEvent;
use App\Models\CrmAssignmentRule;
use App\Models\CrmTask;
use App\Models\Lead;
use App\Models\LeadStage;
use App\Models\Tenant;
use App\Models\User;

beforeEach(function () {
    $this->tenantA = Tenant::factory()->create();
    $this->tenantB = Tenant::factory()->create();
});

it('scopes every CRM model to its tenant', function () {
    $stageA = withinTenant($this->tenantA, fn () => LeadStage::query()->create(['name' => 'Lead', 'slug' => 'lead', 'position' => 1]));
    $stageB = withinTenant($this->tenantB, fn () => LeadStage::query()->create(['name' => 'Lead', 'slug' => 'lead', 'position' => 1]));

    $leadA = Lead::factory()->for($this->tenantA)->create();
    $leadB = Lead::factory()->for($this->tenantB)->create();

    $ruleA = withinTenant($this->tenantA, fn () => CrmAssignmentRule::query()->create(['mode' => 'round_robin', 'sla_minutes' => 15]));
    $ruleB = withinTenant($this->tenantB, fn () => CrmAssignmentRule::query()->create(['mode' => 'round_robin', 'sla_minutes' => 30]));

    $userA = User::factory()->for($this->tenantA)->create();
    $taskA = CrmTask::factory()->for($this->tenantA)->create(['lead_id' => $leadA->id, 'assigned_to' => $userA->id]);
    $userB = User::factory()->for($this->tenantB)->create();
    $taskB = CrmTask::factory()->for($this->tenantB)->create(['lead_id' => $leadB->id, 'assigned_to' => $userB->id]);

    $eventA = ContactTimelineEvent::factory()->for($this->tenantA)->create(['lead_id' => $leadA->id]);
    $eventB = ContactTimelineEvent::factory()->for($this->tenantB)->create(['lead_id' => $leadB->id]);

    withinTenant($this->tenantA, function () use ($stageB, $leadB, $ruleB, $taskB, $eventB) {
        expect(LeadStage::query()->find($stageB->id))->toBeNull();
        expect(Lead::query()->find($leadB->id))->toBeNull();
        expect(CrmAssignmentRule::query()->find($ruleB->id))->toBeNull();
        expect(CrmTask::query()->find($taskB->id))->toBeNull();
        expect(ContactTimelineEvent::query()->find($eventB->id))->toBeNull();
    });

    withinTenant($this->tenantB, function () use ($stageA, $leadA, $ruleA, $taskA, $eventA) {
        expect(LeadStage::query()->find($stageA->id))->toBeNull();
        expect(Lead::query()->find($leadA->id))->toBeNull();
        expect(CrmAssignmentRule::query()->find($ruleA->id))->toBeNull();
        expect(CrmTask::query()->find($taskA->id))->toBeNull();
        expect(ContactTimelineEvent::query()->find($eventA->id))->toBeNull();
    });
});
