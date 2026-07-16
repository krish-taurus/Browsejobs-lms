<?php

declare(strict_types=1);

use App\Actions\Crm\AssignLead;
use App\Actions\Crm\CaptureLead;
use App\Models\AuditLog;
use App\Models\CrmAssignmentRule;
use App\Models\Lead;
use App\Models\Tenant;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
    Queue::fake();
    $this->tenant = Tenant::factory()->create();
    $this->c1 = counselorIn($this->tenant, 'Counselor One');
    $this->c2 = counselorIn($this->tenant, 'Counselor Two');
});

function capture(Tenant $tenant, string $course = 'data-engineering'): Lead
{
    return app(CaptureLead::class)->handle($tenant, [
        'lead_type' => 'masterclass',
        'name' => 'Lead '.fake()->word(),
        'phone' => '+91 9'.fake()->numerify('#########'),
        'course_slug' => $course,
    ]);
}

it('assigns round-robin, advancing the pointer across captures', function () {
    withinTenant($this->tenant, fn () => CrmAssignmentRule::query()->create([
        'mode' => CrmAssignmentRule::MODE_ROUND_ROBIN, 'sla_minutes' => 15,
    ]));

    $l1 = capture($this->tenant);
    $l2 = capture($this->tenant);
    $l3 = capture($this->tenant);

    expect($l1->assigned_to)->toBe($this->c1->id)
        ->and($l2->assigned_to)->toBe($this->c2->id)
        ->and($l3->assigned_to)->toBe($this->c1->id);
});

it('assigns by course when configured, falling back to round-robin for unmapped courses', function () {
    withinTenant($this->tenant, fn () => CrmAssignmentRule::query()->create([
        'mode' => CrmAssignmentRule::MODE_BY_COURSE,
        'by_course_map' => ['data-engineering' => $this->c2->id],
        'sla_minutes' => 15,
    ]));

    $mapped = capture($this->tenant, 'data-engineering');
    $unmapped = capture($this->tenant, 'devops-cloud');

    expect($mapped->assigned_to)->toBe($this->c2->id)
        ->and($unmapped->assigned_to)->toBe($this->c1->id); // round-robin fallback, pointer null -> first
});

it('audits a manual reassignment', function () {
    withinTenant($this->tenant, fn () => CrmAssignmentRule::query()->create([
        'mode' => CrmAssignmentRule::MODE_ROUND_ROBIN, 'sla_minutes' => 15,
    ]));

    $lead = capture($this->tenant); // auto-assigned c1

    withinTenant($this->tenant, fn () => app(AssignLead::class)->handle($lead, $this->c2, $this->c1));

    expect($lead->fresh()->assigned_to)->toBe($this->c2->id)
        ->and(AuditLog::withoutGlobalScopes()->where('action', 'crm.lead_reassigned')->count())->toBe(1);
});
