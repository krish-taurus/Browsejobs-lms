<?php

declare(strict_types=1);

use App\Models\CrmAssignmentRule;
use App\Models\Lead;
use App\Models\LeadStage;
use App\Models\Tenant;
use Database\Seeders\RolePermissionSeeder;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
    $this->tenant = Tenant::factory()->create(['slug' => 'metatenant']);

    withinTenant($this->tenant, function () {
        LeadStage::query()->create(['name' => 'Lead', 'slug' => 'lead', 'position' => 1]);
        CrmAssignmentRule::query()->create(['mode' => CrmAssignmentRule::MODE_ROUND_ROBIN, 'sla_minutes' => 15]);
    });

    config([
        'services.meta.app_secret' => 'meta-secret',
        'services.meta.verify_token' => 'verify-me',
        'services.meta.tenant_slug' => 'metatenant',
    ]);
});

function postMeta(array $payload)
{
    $body = json_encode($payload);
    $signature = 'sha256='.hash_hmac('sha256', $body, 'meta-secret');

    return test()->call('POST', '/api/webhooks/meta/leads', [], [], [], [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_X_HUB_SIGNATURE_256' => $signature,
    ], $body);
}

function leadgenPayload(): array
{
    return [
        'object' => 'page',
        'entry' => [[
            'id' => 'page-1',
            'changes' => [[
                'field' => 'leadgen',
                'value' => [
                    'leadgen_id' => 'lg-1',
                    'form_id' => 'form-1',
                    'field_data' => [
                        ['name' => 'full_name', 'values' => ['Rahul Verma']],
                        ['name' => 'phone_number', 'values' => ['+91 90000 55555']],
                        ['name' => 'email', 'values' => ['rahul@example.com']],
                        ['name' => 'course', 'values' => ['data-engineering']],
                    ],
                ],
            ]],
        ]],
    ];
}

it('answers the subscription verification handshake', function () {
    $this->get('/api/webhooks/meta/leads?hub_mode=subscribe&hub_verify_token=verify-me&hub_challenge=challenge-123')
        ->assertOk()
        ->assertSee('challenge-123');
});

it('rejects the verification handshake with a wrong token', function () {
    $this->get('/api/webhooks/meta/leads?hub_mode=subscribe&hub_verify_token=nope&hub_challenge=x')
        ->assertForbidden();
});

it('rejects an unsigned lead delivery', function () {
    $this->postJson('/api/webhooks/meta/leads', leadgenPayload())->assertForbidden();
});

it('rejects a delivery with a wrong signature', function () {
    test()->call('POST', '/api/webhooks/meta/leads', [], [], [], [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_X_HUB_SIGNATURE_256' => 'sha256=deadbeef',
    ], json_encode(leadgenPayload()))->assertForbidden();
});

it('captures a signed lead delivery onto the pipeline', function () {
    postMeta(leadgenPayload())->assertOk()->assertJson(['ok' => true, 'captured' => 1]);

    $lead = Lead::withoutGlobalScopes()->where('phone_normalized', '919000055555')->first();

    expect($lead)->not->toBeNull()
        ->and($lead->name)->toBe('Rahul Verma')
        ->and($lead->tenant_id)->toBe($this->tenant->id)
        ->and($lead->utm_source)->toBe('meta_lead_ads')
        ->and($lead->lead_stage_id)->not->toBeNull();
});
