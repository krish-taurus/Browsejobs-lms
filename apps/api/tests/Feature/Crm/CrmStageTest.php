<?php

declare(strict_types=1);

use App\Actions\Crm\MoveLeadStage;
use App\Models\AuditLog;
use App\Models\ContactTimelineEvent;
use App\Models\Lead;
use App\Models\LeadStage;
use App\Models\Tenant;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    $this->tenant = Tenant::factory()->create();
    [$this->stage1, $this->stage2] = withinTenant($this->tenant, fn () => [
        LeadStage::query()->create(['name' => 'Lead', 'slug' => 'lead', 'position' => 1]),
        LeadStage::query()->create(['name' => 'Attended', 'slug' => 'attended', 'position' => 2]),
    ]);
    $this->lead = Lead::factory()->for($this->tenant)->create(['lead_stage_id' => $this->stage1->id]);
});

it('moves a lead to a new stage, recording a timeline event and an audit override', function () {
    withinTenant($this->tenant, fn () => app(MoveLeadStage::class)->handle($this->lead, $this->stage2));

    expect($this->lead->fresh()->lead_stage_id)->toBe($this->stage2->id)
        ->and(AuditLog::withoutGlobalScopes()->where('action', 'crm.stage_changed')->count())->toBe(1)
        ->and(ContactTimelineEvent::withoutGlobalScopes()->where('type', ContactTimelineEvent::TYPE_STAGE_CHANGED)->count())->toBe(1);
});

it('is a no-op when the lead is already on the stage', function () {
    withinTenant($this->tenant, fn () => app(MoveLeadStage::class)->handle($this->lead, $this->stage1));

    expect(AuditLog::withoutGlobalScopes()->where('action', 'crm.stage_changed')->count())->toBe(0);
});

it('refuses a stage from another tenant', function () {
    $foreignStage = withinTenant(Tenant::factory()->create(), fn () => LeadStage::query()->create([
        'name' => 'X', 'slug' => 'x', 'position' => 1,
    ]));

    expect(fn () => app(MoveLeadStage::class)->handle($this->lead, $foreignStage))
        ->toThrow(ValidationException::class);
});
