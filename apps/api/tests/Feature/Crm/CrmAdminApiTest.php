<?php

declare(strict_types=1);

use App\Models\CrmAssignmentRule;
use App\Models\Lead;
use App\Models\LeadStage;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
    Queue::fake();
    $this->tenant = Tenant::factory()->create();
    $this->admin = User::factory()->for($this->tenant)->create(['user_type' => 'staff']);
    $this->admin->assignRole('admin');

    withinTenant($this->tenant, function () {
        LeadStage::query()->create(['name' => 'Lead', 'slug' => 'lead', 'position' => 1]);
        LeadStage::query()->create(['name' => 'Attended', 'slug' => 'attended', 'position' => 2]);
        CrmAssignmentRule::query()->create(['mode' => CrmAssignmentRule::MODE_ROUND_ROBIN, 'sla_minutes' => 15]);
    });
});

it('lists leads scoped to the tenant', function () {
    $mine = Lead::factory()->for($this->tenant)->create(['name' => 'Mine']);
    Lead::factory()->for(Tenant::factory()->create())->create(['name' => 'Theirs']);

    Sanctum::actingAs($this->admin);

    $this->getJson('/api/v1/admin/leads')
        ->assertOk()
        ->assertJsonFragment(['id' => $mine->id])
        ->assertJsonMissing(['name' => 'Theirs']);
});

it('404s a foreign tenant lead on show', function () {
    $foreign = Lead::factory()->for(Tenant::factory()->create())->create();

    Sanctum::actingAs($this->admin);

    $this->getJson("/api/v1/admin/leads/{$foreign->id}")->assertNotFound();
});

it('creates a lead by hand', function () {
    Sanctum::actingAs($this->admin);

    $this->postJson('/api/v1/admin/leads', [
        'lead_type' => 'counselling',
        'name' => 'Walk In',
        'phone' => '+91 90000 77777',
    ])->assertCreated()->assertJsonPath('data.name', 'Walk In');

    expect(Lead::withoutGlobalScopes()->where('name', 'Walk In')->exists())->toBeTrue();
});

it('imports leads from CSV and reports a summary', function () {
    Sanctum::actingAs($this->admin);

    $csv = "name,phone,email,lead_type,course_slug\n"
        ."Priya,+91 90000 11111,priya@x.com,masterclass,data-engineering\n"
        ."Dup,+919000011111,,counselling,\n"          // duplicate phone -> skipped
        .",+91 90000 22222,,masterclass,\n";          // missing name -> invalid

    $this->postJson('/api/v1/admin/leads/import', ['csv' => $csv])
        ->assertOk()
        ->assertJsonPath('data.imported', 1)
        ->assertJsonPath('data.duplicates', 1);
});

it('assigns and moves a lead through the API', function () {
    $counselor = counselorIn($this->tenant);
    $lead = Lead::factory()->for($this->tenant)->create();
    $stage2 = LeadStage::withoutGlobalScopes()->where('tenant_id', $this->tenant->id)->where('slug', 'attended')->first();

    Sanctum::actingAs($this->admin);

    $this->postJson("/api/v1/admin/leads/{$lead->id}/assign", ['counselor_id' => $counselor->id])
        ->assertOk()->assertJsonPath('data.assigned_to', $counselor->id);

    $this->postJson("/api/v1/admin/leads/{$lead->id}/stage", ['lead_stage_id' => $stage2->id])
        ->assertOk()->assertJsonPath('data.lead_stage_id', $stage2->id);
});

it('denies a staff user without manage-leads', function () {
    $mentor = User::factory()->for($this->tenant)->create(['user_type' => 'staff']);
    $mentor->assignRole('mentor');

    Sanctum::actingAs($mentor);

    $this->getJson('/api/v1/admin/leads')->assertForbidden();
});
