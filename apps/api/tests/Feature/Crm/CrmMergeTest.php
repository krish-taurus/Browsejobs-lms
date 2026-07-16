<?php

declare(strict_types=1);

use App\Actions\Crm\FindDuplicateLeads;
use App\Actions\Crm\MergeLeads;
use App\Models\AuditLog;
use App\Models\ContactTimelineEvent;
use App\Models\CrmTask;
use App\Models\Lead;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    $this->tenant = Tenant::factory()->create();
});

function makeLead(Tenant $tenant, string $phone, array $extra = []): Lead
{
    return Lead::factory()->for($tenant)->create([
        'phone' => $phone,
        'phone_normalized' => preg_replace('/\D+/', '', $phone),
        ...$extra,
    ]);
}

function crmAuditCount(string $action): int
{
    return AuditLog::withoutGlobalScopes()->where('action', $action)->count();
}

it('detects duplicate leads by normalized phone', function () {
    $a = makeLead($this->tenant, '+91 90000 12345');
    $b = makeLead($this->tenant, '+919000012345');
    makeLead($this->tenant, '+91 90000 99999'); // different phone

    $dupes = withinTenant($this->tenant, fn () => app(FindDuplicateLeads::class)->handle($a));

    expect($dupes->pluck('id')->all())->toBe([$b->id]);
});

it('merges the newer lead into the older, folding fields, timelines and tasks, with an audit', function () {
    $older = makeLead($this->tenant, '+91 90000 12345', ['email' => null, 'course_slug' => null]);
    $newer = makeLead($this->tenant, '+919000012345', ['email' => 'folded@example.com', 'course_slug' => 'data-engineering']);

    $task = CrmTask::factory()->for($this->tenant)->create([
        'lead_id' => $newer->id,
        'assigned_to' => User::factory()->for($this->tenant)->create()->id,
    ]);
    ContactTimelineEvent::factory()->for($this->tenant)->create(['lead_id' => $newer->id]);

    $survivor = withinTenant($this->tenant, fn () => app(MergeLeads::class)->handle($older, $newer));

    expect($survivor->id)->toBe($older->id)
        ->and($survivor->email)->toBe('folded@example.com')
        ->and($survivor->course_slug)->toBe('data-engineering');

    expect($newer->fresh()->merged_into_id)->toBe($older->id)
        ->and($task->fresh()->lead_id)->toBe($older->id)
        ->and(crmAuditCount('crm.lead_merged'))->toBe(1);

    $timelineOnSurvivor = ContactTimelineEvent::withoutGlobalScopes()->where('lead_id', $older->id)->count();
    expect($timelineOnSurvivor)->toBeGreaterThanOrEqual(2); // folded event + merge event
});

it('honours an explicit survivor override', function () {
    $older = makeLead($this->tenant, '+91 90000 12345');
    $newer = makeLead($this->tenant, '+919000012345');

    $survivor = withinTenant($this->tenant, fn () => app(MergeLeads::class)->handle($older, $newer, $newer));

    expect($survivor->id)->toBe($newer->id)
        ->and($older->fresh()->merged_into_id)->toBe($newer->id);
});

it('refuses to merge leads across tenants', function () {
    $a = makeLead($this->tenant, '+91 90000 12345');
    $b = makeLead(Tenant::factory()->create(), '+91 90000 12345');

    expect(fn () => app(MergeLeads::class)->handle($a, $b))->toThrow(ValidationException::class);
});
