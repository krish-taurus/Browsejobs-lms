<?php

declare(strict_types=1);

use App\Models\AuditLog;
use App\Models\CvProfile;
use App\Models\DataRequest;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Laravel\Sanctum\Sanctum;

use function Pest\Laravel\getJson;
use function Pest\Laravel\postJson;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
    $this->tenant = Tenant::factory()->create();
    $this->student = User::factory()->for($this->tenant)->create([
        'user_type' => 'student', 'name' => 'Asha Rao', 'email' => 'asha@example.com', 'phone' => '+919000000001',
    ]);
    $this->officer = User::factory()->for($this->tenant)->create(['user_type' => 'staff']);
    $this->officer->assignRole('super-admin');
    withinTenant($this->tenant, fn () => CvProfile::query()->create([
        'tenant_id' => $this->tenant->id, 'user_id' => $this->student->id,
        'data' => ['summary' => 'Aspiring DE', 'skills' => ['Python']],
    ]));
});

it('lets a student raise an access request, once', function () {
    Sanctum::actingAs($this->student);

    postJson('/api/v1/me/data-requests', ['type' => 'access'])->assertCreated()->assertJsonPath('data.status', 'pending');
    postJson('/api/v1/me/data-requests', ['type' => 'access'])->assertStatus(422); // duplicate pending

    expect(DataRequest::withoutGlobalScopes()->count())->toBe(1);
    expect(AuditLog::query()->where('action', 'data_request.raised')->exists())->toBeTrue();
});

it('fulfils an access request with the student own data only', function () {
    $req = withinTenant($this->tenant, fn () => DataRequest::factory()->for($this->tenant)->create([
        'user_id' => $this->student->id, 'type' => 'access',
    ]));
    Sanctum::actingAs($this->officer);

    postJson("/api/v1/admin/data-requests/{$req->id}/process")->assertOk()->assertJsonPath('data.status', 'completed');

    // The student downloads their export.
    Sanctum::actingAs($this->student);
    $export = getJson("/api/v1/me/data-requests/{$req->id}")->assertOk()->json('data.export');
    expect($export['profile']['email'])->toBe('asha@example.com')
        ->and($export['cv_profile']['skills'])->toBe(['Python']);
});

it('anonymises the student on a deletion request but keeps the account row', function () {
    $req = withinTenant($this->tenant, fn () => DataRequest::factory()->for($this->tenant)->create([
        'user_id' => $this->student->id, 'type' => 'deletion',
    ]));
    Sanctum::actingAs($this->officer);

    postJson("/api/v1/admin/data-requests/{$req->id}/process")->assertOk();

    $user = $this->student->fresh();
    expect($user)->not->toBeNull()                       // record retained (financial/legal)
        ->and($user->name)->toBe('Deleted user '.$user->id)
        ->and($user->email)->toBeNull()
        ->and($user->phone)->toBeNull()
        ->and($user->anonymized_at)->not->toBeNull();
    expect(CvProfile::withoutGlobalScopes()->where('user_id', $user->id)->value('data'))
        ->toBe(CvProfile::EMPTY);
});

it('cannot process a request twice', function () {
    $req = withinTenant($this->tenant, fn () => DataRequest::factory()->for($this->tenant)->create([
        'user_id' => $this->student->id, 'type' => 'access', 'status' => 'completed',
    ]));
    Sanctum::actingAs($this->officer);
    postJson("/api/v1/admin/data-requests/{$req->id}/process")->assertStatus(422);
});

it('rejects a request with a reason', function () {
    $req = withinTenant($this->tenant, fn () => DataRequest::factory()->for($this->tenant)->create([
        'user_id' => $this->student->id, 'type' => 'deletion',
    ]));
    Sanctum::actingAs($this->officer);
    postJson("/api/v1/admin/data-requests/{$req->id}/reject", ['reason' => 'Active fee dispute.'])
        ->assertOk()->assertJsonPath('data.status', 'rejected');
});

it('gates processing behind super-admin', function () {
    $trainer = User::factory()->for($this->tenant)->create(['user_type' => 'staff']);
    $trainer->assignRole('trainer');
    Sanctum::actingAs($trainer);
    getJson('/api/v1/admin/data-requests')->assertForbidden();
});

it('does not expose another students request', function () {
    $other = User::factory()->for($this->tenant)->create(['user_type' => 'student']);
    $req = withinTenant($this->tenant, fn () => DataRequest::factory()->for($this->tenant)->create([
        'user_id' => $other->id, 'type' => 'access',
    ]));
    Sanctum::actingAs($this->student);
    getJson("/api/v1/me/data-requests/{$req->id}")->assertNotFound();
});

it('requires authentication', function () {
    getJson('/api/v1/me/data-requests')->assertUnauthorized();
});
