<?php

declare(strict_types=1);

use App\Models\AuditLog;
use App\Models\TaurusEvent;
use App\Models\TaurusSpendEvent;
use App\Models\TaurusTask;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;
use Tests\Support\TaurusFixtures;

beforeEach(function (): void {
    TaurusFixtures::cleanPlatform();
    config(['taurus.timezone' => 'Asia/Kolkata', 'taurus.offline_after_minutes' => 15]);

    $this->tenant = Tenant::factory()->create();
    $this->workspace = TaurusFixtures::workspace($this->tenant, ['name' => 'Acme Ops']);
    $this->owner = User::factory()->for($this->tenant)->create(['user_type' => 'client']);
    TaurusFixtures::member($this->workspace, $this->owner, 'owner');
    $this->base = "/api/v1/taurus/workspaces/{$this->workspace->id}";
});

afterEach(function (): void {
    Carbon::setTestNow();
});

/** A wall-clock time in IST, expressed in the app timezone the DB stores. */
function ist(string $time): Carbon
{
    return Carbon::parse($time, 'Asia/Kolkata')->setTimezone((string) config('app.timezone'));
}

it('lists only the caller\'s own workspaces', function (): void {
    $other = TaurusFixtures::workspace($this->tenant, ['name' => 'Not Mine']);
    $hq = TaurusFixtures::workspace($this->tenant, ['name' => 'Taurus HQ', 'is_owner' => true]);
    $viewer = User::factory()->for($this->tenant)->create(['user_type' => 'client']);
    TaurusFixtures::member($this->workspace, $viewer, 'viewer');

    Sanctum::actingAs($viewer);
    $this->getJson('/api/v1/taurus/workspaces')
        ->assertOk()
        ->assertExactJson(['data' => [[
            'id' => $this->workspace->id,
            'name' => 'Acme Ops',
            'slug' => $this->workspace->slug,
            'status' => 'active',
            'is_owner' => false,
            'role' => 'viewer',
        ]]]);

    expect([$other->id, $hq->id])->not->toContain($this->workspace->id);
});

it('derives offline from silence and counts KPIs from this workspace only', function (): void {
    Carbon::setTestNow(ist('2026-10-09 11:30:00'));

    $fresh = TaurusFixtures::agent($this->workspace, 'fresh', 'working', 5);
    TaurusFixtures::agent($this->workspace, 'stale', 'working', 20);
    TaurusFixtures::agent($this->workspace, 'never', 'thinking', null);
    TaurusFixtures::agent($this->workspace, 'recruiter', 'working', 1, 'recruitment');

    // A sibling workspace in the same tenant: busy, but none of our business.
    $sibling = TaurusFixtures::workspace($this->tenant);
    TaurusFixtures::task(TaurusFixtures::agent($sibling, 'fresh', 'working', 1));

    TaurusFixtures::task($fresh);
    TaurusFixtures::task($fresh, 'a', ['status' => 'done', 'finished_at' => ist('2026-10-09 00:30:00')]);
    TaurusFixtures::task($fresh, 'b', ['status' => 'done', 'finished_at' => ist('2026-10-08 23:30:00')]);
    TaurusFixtures::task($fresh, 'c', ['status' => 'failed', 'finished_at' => ist('2026-10-09 07:30:00')]);

    Sanctum::actingAs($this->owner);
    $data = $this->getJson("{$this->base}/state?floor=ops")->assertOk()->json('data');

    expect(collect($data['agents'])->pluck('status', 'slug')->all())
        ->toBe(['fresh' => 'working', 'never' => 'offline', 'stale' => 'offline'])
        ->and($data['kpis'])->toBe([
            'running' => 1,
            'needs' => 1,
            'done_today' => 1,
            'failed_today' => 1,
            'online' => 1,
            'agents' => 3,
        ])
        ->and(collect($data['tasks'])->pluck('ref')->sort()->values()->all())->toBe(['a', 'c', 'pay-1']);
});

it('reports spend exactly as the bots did', function (): void {
    Carbon::setTestNow(ist('2026-10-09 11:30:00'));
    $agent = TaurusFixtures::agent($this->workspace, 'ledger');

    $row = fn (array $attrs) => TaurusSpendEvent::query()->create([
        'tenant_id' => $this->tenant->id, 'taurus_workspace_id' => $this->workspace->id,
        'agent_id' => $agent->id, 'occurred_at' => now(), ...$attrs,
    ]);
    $row(['source' => 'openai', 'currency' => 'USD', 'amount_micros' => 1_250_000]);
    $row(['source' => 'openai', 'currency' => 'USD', 'amount_micros' => 250_000]);
    $row(['source' => 'apify', 'units' => 3, 'unit_label' => 'runs']);
    $row(['source' => 'openai', 'currency' => 'USD', 'amount_micros' => 9_000_000, 'occurred_at' => now()->subDays(3)]);

    Sanctum::actingAs($this->owner);
    $spend = $this->getJson("{$this->base}/state")->assertOk()->json('data.spend');

    $today = collect($spend['today'])->keyBy('source');
    expect($today['openai']['amount'])->toEqual(1.5)
        ->and($today['apify']['amount'])->toBeNull()
        ->and($today['apify']['units'])->toEqual(3)
        ->and($spend['by_agent'][0]['agent_name'])->toBe('Ledger')
        ->and($spend['days'])->toHaveCount(14)
        ->and(collect($spend['days'])->last())->toEqual(['date' => '2026-10-09', 'currency' => 'USD', 'amount' => 1.5]);
});

it('runs the approval loop: bot asks, owner approves, only this workspace\'s bot reads it', function (): void {
    $token = TaurusFixtures::token($this->workspace);
    $other = TaurusFixtures::workspace($this->tenant);
    $otherToken = TaurusFixtures::token($other);

    $this->withHeaders(['Authorization' => 'Bearer '.$token])->postJson('/api/v1/taurus/ingest', [
        'events' => [[
            'agent' => ['slug' => 'payer', 'name' => 'Payer'],
            'task' => ['ref' => 'pay-9', 'title' => 'Pay vendor', 'approval' => ['action' => 'Pay ₹10,000 to Acme', 'risk' => 'high']],
        ]],
    ])->assertStatus(202);
    $task = TaurusTask::withoutGlobalScopes()->sole();

    Sanctum::actingAs($this->owner);
    $approved = $this->postJson("{$this->base}/tasks/{$task->id}/approve", ['note' => 'Go ahead'])
        ->assertOk()
        ->assertJsonPath('data.status', 'running')
        ->assertJsonPath('data.decision', 'approved')
        ->assertJsonPath('data.agent_name', 'Payer')
        ->assertJsonPath('data.approval_action', 'Pay ₹10,000 to Acme')
        ->json('data');

    expect(array_keys($approved))->toBe([
        'id', 'agent_id', 'agent_name', 'ref', 'title', 'status', 'progress', 'risk',
        'approval_action', 'decision', 'created_at', 'updated_at', 'finished_at',
    ]);

    expect(TaurusEvent::withoutGlobalScopes()->where('kind', 'approval')->sole()->taurus_workspace_id)->toBe($this->workspace->id)
        ->and(AuditLog::query()->where('action', 'taurus.task.approved')->sole()->metadata['ref'])->toBe('pay-9');

    app('auth')->forgetGuards();
    $since = urlencode(now()->subMinute()->toIso8601String());
    $this->withHeaders(['Authorization' => 'Bearer '.$token])
        ->getJson("/api/v1/taurus/decisions?since={$since}")
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.task_ref', 'pay-9')
        ->assertJsonPath('data.0.decision', 'approved')
        ->assertJsonPath('data.0.note', 'Go ahead');

    // Another workspace's bot sees nothing of it.
    $this->withHeaders(['Authorization' => 'Bearer '.$otherToken])
        ->getJson("/api/v1/taurus/decisions?since={$since}")
        ->assertOk()
        ->assertJsonCount(0, 'data');
});

it('rejects a task and refuses to decide one twice', function (): void {
    $agent = TaurusFixtures::agent($this->workspace, 'payer', 'needs');
    $task = TaurusFixtures::task($agent);

    Sanctum::actingAs($this->owner);
    $this->postJson("{$this->base}/tasks/{$task->id}/reject")
        ->assertOk()
        ->assertJsonPath('data.status', 'rejected');

    $this->postJson("{$this->base}/tasks/{$task->id}/approve")
        ->assertStatus(409)
        ->assertJsonPath('error.code', 'not_awaiting_approval');
});

it('hides workspace B from a member of workspace A (404, not 403)', function (): void {
    $b = TaurusFixtures::workspace($this->tenant, ['name' => 'Workspace B']);
    TaurusFixtures::member($b, User::factory()->for($this->tenant)->create(), 'owner');
    $bTask = TaurusFixtures::task(TaurusFixtures::agent($b, 'payer'));

    Sanctum::actingAs($this->owner);
    $this->getJson("/api/v1/taurus/workspaces/{$b->id}/state")->assertNotFound();
    $this->getJson("/api/v1/taurus/workspaces/{$b->id}/brain")->assertNotFound();
    $this->postJson("/api/v1/taurus/workspaces/{$b->id}/tasks/{$bTask->id}/approve")->assertNotFound();
    $this->postJson("/api/v1/taurus/workspaces/{$b->id}/ask", ['question' => 'hi'])->assertNotFound();

    // B's task through A's URL: the task is scoped to A, so it does not exist.
    $this->postJson("{$this->base}/tasks/{$bTask->id}/approve")->assertNotFound();

    expect($bTask->refresh()->status->value)->toBe('needs_approval');
});

it('hides another tenant\'s workspace', function (): void {
    $foreignTenant = Tenant::factory()->create();
    $foreign = TaurusFixtures::workspace($foreignTenant);
    $stranger = User::factory()->for($foreignTenant)->create();
    TaurusFixtures::member($foreign, $stranger, 'owner');

    Sanctum::actingAs($stranger);
    $this->getJson("{$this->base}/state")->assertNotFound();
    $this->getJson("{$this->base}/brain")->assertNotFound();
});

it('lets a viewer watch and ask but not manage', function (): void {
    $viewer = User::factory()->for($this->tenant)->create(['user_type' => 'client']);
    TaurusFixtures::member($this->workspace, $viewer, 'viewer');
    $task = TaurusFixtures::task(TaurusFixtures::agent($this->workspace, 'payer'));

    Sanctum::actingAs($viewer);
    $this->getJson("{$this->base}/state")->assertOk();

    $this->postJson("{$this->base}/tasks/{$task->id}/approve")
        ->assertForbidden()
        ->assertJsonPath('error.code', 'forbidden');
    $this->putJson("{$this->base}/brain", ['brain_model' => 'x'])->assertForbidden();
    $this->getJson("{$this->base}/brain")->assertForbidden();
    $this->postJson("{$this->base}/ingest-token")->assertForbidden();
    $this->getJson("{$this->base}/members")->assertForbidden();

    expect($task->refresh()->status->value)->toBe('needs_approval');
});

it('locks a suspended workspace for its members', function (): void {
    $this->workspace->forceFill(['status' => 'suspended'])->save();

    Sanctum::actingAs($this->owner);
    $this->getJson("{$this->base}/state")
        ->assertForbidden()
        ->assertJsonPath('error.code', 'workspace_suspended');
    $this->getJson("{$this->base}/brain")->assertForbidden()->assertJsonPath('error.code', 'workspace_suspended');

    // The switcher still lists it, marked suspended.
    $this->getJson('/api/v1/taurus/workspaces')->assertOk()->assertJsonPath('data.0.status', 'suspended');
});

it('lists members for owners and admins', function (): void {
    $admin = User::factory()->for($this->tenant)->create(['name' => 'Asha Admin', 'email' => 'asha@example.com']);
    TaurusFixtures::member($this->workspace, $admin, 'admin');

    Sanctum::actingAs($admin);
    $rows = $this->getJson("{$this->base}/members")->assertOk()->json('data');

    expect($rows)->toHaveCount(2)
        ->and(collect($rows)->firstWhere('email', 'asha@example.com'))->toMatchArray(['name' => 'Asha Admin', 'role' => 'admin'])
        ->and(array_keys($rows[0]))->toBe(['id', 'name', 'email', 'role']);
});
