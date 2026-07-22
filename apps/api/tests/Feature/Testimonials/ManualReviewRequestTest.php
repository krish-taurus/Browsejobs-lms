<?php

declare(strict_types=1);

use App\Models\Batch;
use App\Models\BatchMember;
use App\Models\Message;
use App\Models\MessageTemplate;
use App\Models\Tenant;
use App\Models\Testimonial;
use App\Models\User;
use App\Support\WhatsApp\FakeWhatsAppClient;
use App\Support\WhatsApp\WhatsAppClient;
use Database\Seeders\RolePermissionSeeder;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    app()->instance(WhatsAppClient::class, new FakeWhatsAppClient);
    $this->seed(RolePermissionSeeder::class);
    $this->tenant = Tenant::factory()->domain('mrq.test')->create();
    MessageTemplate::factory()->for($this->tenant)->create([
        'key' => 'review_request', 'channel' => 'whatsapp', 'body' => 'Hi {{name}}: {{link}}',
    ]);
    $this->admin = User::factory()->for($this->tenant)->create(['user_type' => 'staff']);
    $this->admin->assignRole('admin');
});

function makeStudent(Tenant $tenant, string $phone): User
{
    return User::factory()->for($tenant)->create(['user_type' => 'student', 'phone' => $phone]);
}

function requestsSent(): int
{
    return Message::withoutGlobalScopes()->where('template_key', 'review_request')->count();
}

it('sends a manual request to every attendee of a batch', function () {
    $batch = Batch::factory()->for($this->tenant)->create(['type' => 'paid']);
    foreach (['+91 90000 10001', '+91 90000 10002'] as $p) {
        BatchMember::factory()->for($this->tenant)->create(['batch_id' => $batch->id, 'user_id' => makeStudent($this->tenant, $p)->id]);
    }

    Sanctum::actingAs($this->admin);

    $this->postJson('/api/v1/admin/review-requests', ['batch_id' => $batch->id])
        ->assertOk()
        ->assertJsonPath('data.sent', 2)
        ->assertJsonPath('data.targeted', 2);

    expect(requestsSent())->toBe(2);
});

it('sends a manual request to a chosen set of students', function () {
    $a = makeStudent($this->tenant, '+91 90000 20001');
    $b = makeStudent($this->tenant, '+91 90000 20002');

    Sanctum::actingAs($this->admin);

    $this->postJson('/api/v1/admin/review-requests', ['user_ids' => [$a->id, $b->id]])
        ->assertOk()
        ->assertJsonPath('data.sent', 2);

    expect(requestsSent())->toBe(2);
});

it('forces past the per-stage dedup by default', function () {
    $s = makeStudent($this->tenant, '+91 90000 30001');
    // Already engaged with the manual stage — a normal auto-send would skip.
    Testimonial::factory()->for($this->tenant)->create([
        'user_id' => $s->id, 'stage' => 'manual', 'status' => 'approved',
    ]);

    Sanctum::actingAs($this->admin);

    $this->postJson('/api/v1/admin/review-requests', ['user_ids' => [$s->id], 'force' => true])
        ->assertOk()
        ->assertJsonPath('data.sent', 1);

    expect(requestsSent())->toBe(1);
});

it('respects the dedup when force is off', function () {
    $s = makeStudent($this->tenant, '+91 90000 40001');
    Testimonial::factory()->for($this->tenant)->create([
        'user_id' => $s->id, 'stage' => 'manual', 'status' => 'pending',
    ]);

    Sanctum::actingAs($this->admin);

    $this->postJson('/api/v1/admin/review-requests', ['user_ids' => [$s->id], 'force' => false])
        ->assertOk()
        ->assertJsonPath('data.sent', 0)
        ->assertJsonPath('data.skipped', 1);

    expect(requestsSent())->toBe(0);
});

it('requires at least one target', function () {
    Sanctum::actingAs($this->admin);

    $this->postJson('/api/v1/admin/review-requests', [])->assertStatus(422);
});

it('never targets another tenant\'s students', function () {
    $other = Tenant::factory()->create();
    $foreign = makeStudent($other, '+91 90000 50001');

    Sanctum::actingAs($this->admin);

    // A foreign user id resolves to no in-tenant target → validation error, nothing sent.
    $this->postJson('/api/v1/admin/review-requests', ['user_ids' => [$foreign->id]])->assertStatus(422);

    expect(requestsSent())->toBe(0);
});

it('denies staff without manage-vouchers', function () {
    $support = User::factory()->for($this->tenant)->create(['user_type' => 'staff']);
    $support->assignRole('support-agent');
    $s = makeStudent($this->tenant, '+91 90000 60001');

    Sanctum::actingAs($support);

    $this->postJson('/api/v1/admin/review-requests', ['user_ids' => [$s->id]])->assertForbidden();
});
