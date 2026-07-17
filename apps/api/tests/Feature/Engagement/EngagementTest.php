<?php

declare(strict_types=1);

use App\Actions\Engagement\SendWeeklyPulse;
use App\Enums\ActivityType;
use App\Models\ActivityEvent;
use App\Models\Batch;
use App\Models\BatchMember;
use App\Models\Celebration;
use App\Models\CelebrationGuidance;
use App\Models\Course;
use App\Models\InAppNotification;
use App\Models\MessagePreference;
use App\Models\MessageTemplate;
use App\Models\PulseDigest;
use App\Models\Tenant;
use App\Models\User;
use App\Services\AI\AiClient;
use App\Services\AI\FakeAiClient;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);

    $this->fake = new FakeAiClient;
    app()->instance(AiClient::class, $this->fake);

    $this->tenant = Tenant::factory()->create();
    $this->admin = User::factory()->for($this->tenant)->create(['user_type' => 'staff']);
    $this->admin->assignRole('admin');

    $course = Course::factory()->for($this->tenant)->create();
    $this->batch = Batch::factory()->for($this->tenant)->create(['course_id' => $course->id]);

    $this->star = enrolStudent($this->tenant, $this->batch, 'Asha Star');
    $this->mate = enrolStudent($this->tenant, $this->batch, 'Ravi Mate');
});

function enrolStudent(Tenant $tenant, Batch $batch, string $name): User
{
    $user = User::factory()->for($tenant)->create(['user_type' => 'student', 'name' => $name]);
    BatchMember::factory()->for($tenant)->create([
        'batch_id' => $batch->id, 'user_id' => $user->id, 'status' => 'enrolled',
    ]);

    return $user;
}

/* --------------------------------------------------------------- celebrations */

it('refuses to create a celebration without recorded consent', function () {
    Sanctum::actingAs($this->admin);

    $this->postJson('/api/v1/admin/celebrations', [
        'student_id' => $this->star->id, 'display_mode' => 'named',
        'role_title' => 'Data Engineer', 'consent' => false,
    ])->assertStatus(422);

    expect(Celebration::withoutGlobalScopes()->count())->toBe(0);
});

it('publishes a celebration once, broadcasting in-app to batchmates only', function () {
    Sanctum::actingAs($this->admin);

    $id = $this->postJson('/api/v1/admin/celebrations', [
        'student_id' => $this->star->id, 'display_mode' => 'named',
        'role_title' => 'Data Engineer', 'company' => 'Acme', 'consent' => true,
    ])->assertCreated()->json('data.id');

    // A student in ANOTHER tenant must never hear about it.
    $otherTenant = Tenant::factory()->create();
    $otherCourse = Course::factory()->for($otherTenant)->create();
    $otherBatch = Batch::factory()->for($otherTenant)->create(['course_id' => $otherCourse->id]);
    $foreign = enrolStudent($otherTenant, $otherBatch, 'Foreign');

    $this->postJson("/api/v1/admin/celebrations/{$id}/publish")->assertOk();
    $this->postJson("/api/v1/admin/celebrations/{$id}/publish")->assertOk(); // idempotent

    expect(InAppNotification::withoutGlobalScopes()->where('user_id', $this->mate->id)->where('title', 'like', '%Asha Star%')->count())->toBe(1)
        ->and(InAppNotification::withoutGlobalScopes()->where('user_id', $this->star->id)->count())->toBe(0)
        ->and(InAppNotification::withoutGlobalScopes()->where('user_id', $foreign->id)->count())->toBe(0);
});

it('shows the anonymous label on the wall in anonymous mode', function () {
    Celebration::query()->create([
        'tenant_id' => $this->tenant->id, 'student_id' => $this->star->id,
        'display_mode' => 'anonymous', 'anonymous_label' => 'A Data Engineering student from batch DE-202605',
        'role_title' => 'Data Engineer', 'consented_at' => now(), 'published_at' => now(),
    ]);

    Sanctum::actingAs($this->mate);
    $wall = $this->getJson('/api/v1/me/pulse')->assertOk()->json('data.celebrations');

    expect($wall[0]['display'])->toBe('A Data Engineering student from batch DE-202605')
        ->and(json_encode($wall))->not->toContain('Asha Star');
});

/* ------------------------------------------------------------------- guidance */

function celebrationFixture(Tenant $tenant, User $star): Celebration
{
    return Celebration::query()->create([
        'tenant_id' => $tenant->id, 'student_id' => $star->id, 'display_mode' => 'named',
        'role_title' => 'Data Engineer', 'consented_at' => now(), 'published_at' => now(),
    ]);
}

it('generates a three-action AI guidance card and caches it', function () {
    $celebration = celebrationFixture($this->tenant, $this->star);
    $this->fake->reply = "The gap is specific, not mysterious.\n- Redo the SQL lab this week\n- Rewatch Spark module and take notes\n- Pass the Foundations quiz at 100%";

    Sanctum::actingAs($this->mate);

    $first = $this->postJson("/api/v1/me/pulse/celebrations/{$celebration->id}/guidance")
        ->assertOk()->json('data');

    expect($first['actions'])->toHaveCount(3)
        ->and($first['source'])->toBe('ai')
        ->and($first['intro'])->toContain('not mysterious');

    // Second request returns the cache — still exactly one stored card.
    $this->postJson("/api/v1/me/pulse/celebrations/{$celebration->id}/guidance")->assertOk();
    expect(CelebrationGuidance::withoutGlobalScopes()->count())->toBe(1);
});

it('falls back to deterministic actions when the AI budget is exhausted', function () {
    config(['ai.daily_token_budget' => 0]);
    $celebration = celebrationFixture($this->tenant, $this->star);

    Sanctum::actingAs($this->mate);
    $card = $this->postJson("/api/v1/me/pulse/celebrations/{$celebration->id}/guidance")
        ->assertOk()->json('data');

    expect($card['source'])->toBe('fallback')
        ->and($card['actions'])->toHaveCount(3);
});

/* ---------------------------------------------------------------- market pulse */

it('curates items and builds an AI digest with sources', function () {
    Sanctum::actingAs($this->admin);

    $this->postJson('/api/v1/admin/pulse/items', [
        'title' => 'Data engineering postings up in Bengaluru',
        'url' => 'https://example.test/report', 'source_name' => 'Example Jobs Report',
    ])->assertCreated();

    $this->fake->reply = 'Data engineering demand is climbing (Example Jobs Report).';

    $this->postJson('/api/v1/admin/pulse/generate')->assertOk()
        ->assertJsonPath('data.source', 'ai');

    Sanctum::actingAs($this->mate);
    $pulse = $this->getJson('/api/v1/me/pulse')->assertOk()->json('data.digest');

    expect($pulse['narrative'])->toContain('Example Jobs Report')
        ->and($pulse['sources'][0]['source_name'])->toBe('Example Jobs Report');
});

it('refuses to generate a digest with nothing curated', function () {
    Sanctum::actingAs($this->admin);

    $this->postJson('/api/v1/admin/pulse/generate')->assertStatus(422);
    expect(PulseDigest::withoutGlobalScopes()->count())->toBe(0);
});

it('sends the weekly pulse only to marketing-opted-in students', function () {
    // Pin to mid-morning: marketing sends are suppressed during quiet hours,
    // so an unpinned clock makes this test fail when the suite runs at night.
    Carbon\Carbon::setTestNow(now()->setTime(11, 0));

    foreach (['whatsapp', 'email'] as $channel) {
        MessageTemplate::query()->create([
            'tenant_id' => $this->tenant->id, 'key' => 'market_pulse_weekly', 'channel' => $channel,
            'category' => 'marketing', 'name' => 'Weekly pulse', 'subject' => 'Pulse', 'body' => 'Hi {{name}}: {{body}}', 'active' => true,
        ]);
    }

    PulseDigest::query()->create([
        'tenant_id' => $this->tenant->id, 'digest_date' => now()->toDateString(),
        'narrative' => 'Market is moving.', 'item_ids' => [], 'source' => 'fallback',
    ]);

    MessagePreference::query()->create([
        'tenant_id' => $this->tenant->id, 'user_id' => $this->mate->id,
        'preferred_channel' => 'whatsapp', 'marketing_opt_in' => true,
    ]);
    MessagePreference::query()->create([
        'tenant_id' => $this->tenant->id, 'user_id' => $this->star->id,
        'preferred_channel' => 'whatsapp', 'marketing_opt_in' => false,
    ]);

    app(SendWeeklyPulse::class)->handle();

    $statusFor = fn (User $u) => DB::table('messages')
        ->where('user_id', $u->id)->where('template_key', 'market_pulse_weekly')
        ->value('status');

    expect($statusFor($this->mate))->not->toBe('suppressed')
        ->and($statusFor($this->star))->toBe('suppressed');
});

/* ----------------------------------------------------------------- content hub */

it('publishes a content release, notifies students, and tracks views into telemetry', function () {
    Sanctum::actingAs($this->admin);

    $itemId = $this->postJson('/api/v1/admin/content-hub', [
        'kind' => 'podcast', 'title' => 'Cracking DE interviews', 'url' => 'https://example.test/ep1',
    ])->assertCreated()->json('data.id');

    expect(InAppNotification::withoutGlobalScopes()->where('title', 'like', 'New episode%')->count())->toBe(2);

    Sanctum::actingAs($this->mate);
    $this->getJson('/api/v1/me/pulse')->assertOk()->assertJsonPath('data.content.0.title', 'Cracking DE interviews');

    $this->postJson("/api/v1/me/pulse/content/{$itemId}/viewed")->assertOk();

    expect(ActivityEvent::withoutGlobalScopes()
        ->where('user_id', $this->mate->id)
        ->where('type', ActivityType::ContentViewed->value)
        ->count())->toBe(1);
});

/* --------------------------------------------------------------------- access */

it('forbids students from the engagement admin endpoints', function () {
    Sanctum::actingAs($this->mate);

    $this->getJson('/api/v1/admin/celebrations')->assertForbidden();
    $this->postJson('/api/v1/admin/pulse/items', [])->assertForbidden();
    $this->postJson('/api/v1/admin/content-hub', [])->assertForbidden();
});
