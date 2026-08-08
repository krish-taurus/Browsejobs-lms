<?php

declare(strict_types=1);

use App\Models\Lead;
use App\Models\Tenant;

beforeEach(function () {
    $this->tenant = Tenant::factory()->domain('acme.test')->create();
});

function postLead(array $overrides = [])
{
    return test()->postJson('http://acme.test/api/v1/leads', [
        'lead_type' => 'masterclass',
        'name' => 'Asha Rao',
        'phone' => '+919000000001',
        'email' => 'asha@example.test',
        'course_slug' => 'data-engineering',
        'utm_source' => 'meta',
        'utm_medium' => 'cpc',
        'utm_campaign' => 'de-july',
        'page' => '/',
        'consent' => true,
        ...$overrides,
    ]);
}

it('captures a masterclass lead with UTM attribution and logged consent', function () {
    postLead()->assertCreated()->assertJson(['status' => 'received']);

    $lead = Lead::withoutGlobalScopes()->first();

    expect($lead->tenant_id)->toBe($this->tenant->id)
        ->and($lead->lead_type)->toBe('masterclass')
        ->and($lead->utm_source)->toBe('meta')
        ->and($lead->utm_campaign)->toBe('de-july')
        ->and($lead->consented_at)->not->toBeNull();
});

it('rejects a lead without consent (DPDP)', function () {
    postLead(['consent' => false])->assertStatus(422);
    expect(Lead::withoutGlobalScopes()->count())->toBe(0);
});

it('rejects an invalid lead type', function () {
    postLead(['lead_type' => 'spam'])->assertStatus(422);
});

it('captures an employer hiring enquiry and scores it as high intent', function () {
    postLead([
        'lead_type' => 'employer',
        'name' => 'Divya Prasad',
        'email' => 'divya@meridian.test',
        'course_slug' => null,
        'message' => "Company: Meridian Logistics\nRoles: Data engineer\nOpenings: 3–5\nTimeline: Hiring now",
    ])->assertCreated();

    $lead = Lead::withoutGlobalScopes()->first();

    expect($lead->lead_type)->toBe('employer')
        ->and($lead->message)->toContain('Meridian Logistics')
        // The employer type is a HOT_TYPE, so the enquiry must not land in the
        // inbox scoring zero behind a brochure download.
        ->and($lead->score)->toBeGreaterThanOrEqual(40);
});

it('requires name and phone', function () {
    postLead(['name' => '', 'phone' => ''])->assertStatus(422);
});

it('scopes leads to the resolving tenant', function () {
    $other = Tenant::factory()->domain('other.test')->create();

    postLead()->assertCreated();

    withinTenant($other, function () {
        expect(Lead::query()->count())->toBe(0);
    });
    withinTenant($this->tenant, function () {
        expect(Lead::query()->count())->toBe(1);
    });
});
