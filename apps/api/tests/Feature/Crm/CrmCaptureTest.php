<?php

declare(strict_types=1);

use App\Actions\Crm\CaptureLead;
use App\Models\ContactTimelineEvent;
use App\Models\CrmAssignmentRule;
use App\Models\Lead;
use App\Models\LeadStage;
use App\Models\Tenant;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
    $this->tenant = Tenant::factory()->create();

    withinTenant($this->tenant, function () {
        LeadStage::query()->create(['name' => 'Lead', 'slug' => 'lead', 'position' => 1]);
        LeadStage::query()->create(['name' => 'Registered', 'slug' => 'registered', 'position' => 2]);
        CrmAssignmentRule::query()->create(['mode' => CrmAssignmentRule::MODE_ROUND_ROBIN, 'sla_minutes' => 15]);
    });

    $this->counselor = counselorIn($this->tenant);
});

it('captures a lead onto the first stage, scores it, assigns a counselor, and arms the SLA', function () {
    Queue::fake();

    $lead = app(CaptureLead::class)->handle($this->tenant, [
        'lead_type' => 'masterclass',
        'name' => 'Priya Sharma',
        'phone' => '+91 90000 00001',
        'email' => 'priya@example.com',
        'course_slug' => 'data-engineering',
        'utm_source' => 'meta',
    ]);

    $first = withinTenant($this->tenant, fn () => LeadStage::query()->orderBy('position')->first());

    expect($lead->lead_stage_id)->toBe($first->id)
        ->and($lead->assigned_to)->toBe($this->counselor->id)
        ->and($lead->phone_normalized)->toBe('919000000001')
        ->and($lead->score)->toBe(100) // masterclass(40)+email(20)+live course(25)+utm(15)
        ->and($lead->sla_due_at)->not->toBeNull();

    $events = withinTenant($this->tenant, fn () => ContactTimelineEvent::query()->where('lead_id', $lead->id)->pluck('type'));
    expect($events)->toContain(ContactTimelineEvent::TYPE_CREATED)
        ->and($events)->toContain(ContactTimelineEvent::TYPE_ASSIGNED);
});

it('leaves a lead unassigned when the tenant has no counselors', function () {
    Queue::fake();
    $this->counselor->roles()->detach();

    $lead = app(CaptureLead::class)->handle($this->tenant, [
        'lead_type' => 'contact',
        'name' => 'No Counselor',
        'phone' => '+91 90000 00002',
    ]);

    expect($lead->assigned_to)->toBeNull();
});

it('scopes captured leads to the resolving tenant', function () {
    Queue::fake();
    $other = Tenant::factory()->create();

    $lead = app(CaptureLead::class)->handle($this->tenant, [
        'lead_type' => 'masterclass', 'name' => 'A', 'phone' => '+91 90000 00003',
    ]);

    withinTenant($other, function () use ($lead) {
        expect(Lead::query()->find($lead->id))->toBeNull();
    });
});
