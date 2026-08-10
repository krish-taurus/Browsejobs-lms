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

it('requires name and phone', function () {
    postLead(['name' => '', 'phone' => ''])->assertStatus(422);
});

it('captures an employer enquiry with the hiring company name', function () {
    postLead([
        'lead_type' => 'employer',
        'name' => 'Meera Iyer',
        'company' => 'Northwind Technologies',
        'course_slug' => null,
        'message' => 'Hiring 12 QA engineers this quarter.',
        'page' => '/employers',
    ])->assertCreated();

    $lead = Lead::withoutGlobalScopes()->first();

    expect($lead->lead_type)->toBe('employer')
        ->and($lead->company)->toBe('Northwind Technologies')
        ->and($lead->message)->toBe('Hiring 12 QA engineers this quarter.')
        ->and($lead->page)->toBe('/employers');
});

it('rejects an employer enquiry without consent (DPDP)', function () {
    postLead(['lead_type' => 'employer', 'company' => 'Northwind', 'consent' => false])->assertStatus(422);
    expect(Lead::withoutGlobalScopes()->count())->toBe(0);
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
