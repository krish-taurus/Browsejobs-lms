<?php

declare(strict_types=1);

use App\Actions\Taurus\IngestEvents;
use App\Models\TaurusAgent;
use App\Models\TaurusEvent;
use App\Models\TaurusSpendEvent;
use App\Models\TaurusTask;
use App\Models\Tenant;
use Tests\Support\TaurusFixtures;

beforeEach(function (): void {
    $this->tenant = Tenant::factory()->create();
    $this->workspace = TaurusFixtures::workspace($this->tenant, ['name' => 'Acme Ops']);
    $this->token = TaurusFixtures::token($this->workspace);
    $this->auth = ['Authorization' => 'Bearer '.$this->token];
});

function taurusReport(array $overrides = []): array
{
    return array_replace_recursive([
        'floor' => 'ops',
        'events' => [[
            'agent' => ['slug' => 'ledger-bot', 'name' => 'Ledger', 'zone' => 'Finance', 'role' => 'Invoices', 'platform' => 'n8n'],
            'status' => 'working',
            'task' => ['ref' => 'inv-42', 'title' => 'Send October invoices', 'status' => 'running', 'progress' => 0.4],
            'message' => 'Drafting 12 invoices',
            'spend' => ['source' => 'openai', 'amount' => '0.1', 'currency' => 'usd', 'units' => 1200, 'unit_label' => 'tokens'],
        ]],
    ], $overrides);
}

it('refuses a request without a bearer token', function (): void {
    $this->postJson('/api/v1/taurus/ingest', taurusReport())
        ->assertUnauthorized()
        ->assertJsonPath('error.code', 'unauthorized');

    expect(TaurusAgent::withoutGlobalScopes()->count())->toBe(0);
});

it('refuses a wrong token', function (): void {
    $this->withHeaders(['Authorization' => 'Bearer tau_wrong'])
        ->postJson('/api/v1/taurus/ingest', taurusReport())
        ->assertUnauthorized()
        ->assertJsonPath('error.code', 'unauthorized');
});

it('refuses a workspace that has no token yet, and an empty bearer', function (): void {
    $fresh = TaurusFixtures::workspace($this->tenant);
    expect($fresh->ingest_token_hash)->toBeNull();

    $this->withHeaders(['Authorization' => 'Bearer '])
        ->postJson('/api/v1/taurus/ingest', taurusReport())
        ->assertUnauthorized();
    $this->getJson('/api/v1/taurus/decisions')->assertUnauthorized();
});

it('routes each token to its own workspace and never another', function (): void {
    $otherTenant = Tenant::factory()->create();
    $other = TaurusFixtures::workspace($otherTenant, ['name' => 'Rival Co']);
    $otherToken = TaurusFixtures::token($other);
    $sibling = TaurusFixtures::workspace($this->tenant, ['name' => 'Sibling Co']);
    TaurusFixtures::token($sibling);

    $this->withHeaders($this->auth)->postJson('/api/v1/taurus/ingest', taurusReport())->assertStatus(202);
    $this->withHeaders(['Authorization' => 'Bearer '.$otherToken])->postJson('/api/v1/taurus/ingest', [
        'events' => [['agent' => ['slug' => 'ledger-bot'], 'status' => 'idle']],
    ])->assertStatus(202);

    $mine = TaurusAgent::withoutGlobalScopes()->where('taurus_workspace_id', $this->workspace->id)->sole();
    $theirs = TaurusAgent::withoutGlobalScopes()->where('taurus_workspace_id', $other->id)->sole();

    // Same slug, two workspaces, two tenants — never merged.
    expect($mine->tenant_id)->toBe($this->tenant->id)
        ->and($mine->status->value)->toBe('working')
        ->and($theirs->tenant_id)->toBe($otherTenant->id)
        ->and($theirs->status->value)->toBe('idle')
        ->and(TaurusAgent::withoutGlobalScopes()->where('taurus_workspace_id', $sibling->id)->exists())->toBeFalse();

    foreach ([TaurusTask::class, TaurusEvent::class, TaurusSpendEvent::class] as $model) {
        expect($model::withoutGlobalScopes()->where('taurus_workspace_id', '!=', $this->workspace->id)->where('tenant_id', $this->tenant->id)->exists())->toBeFalse();
    }
});

it('refuses a suspended workspace', function (): void {
    $this->workspace->forceFill(['status' => 'suspended'])->save();

    $this->withHeaders($this->auth)
        ->postJson('/api/v1/taurus/ingest', taurusReport())
        ->assertForbidden()
        ->assertJsonPath('error.code', 'workspace_suspended');
    $this->withHeaders($this->auth)->getJson('/api/v1/taurus/decisions')->assertForbidden();

    expect(TaurusAgent::withoutGlobalScopes()->count())->toBe(0);
});

it('stops accepting a rotated-out token', function (): void {
    $old = $this->auth;
    TaurusFixtures::token($this->workspace);

    $this->withHeaders($old)->postJson('/api/v1/taurus/ingest', taurusReport())->assertUnauthorized();
});

it('creates the agent, task, feed lines and spend from one report', function (): void {
    $this->withHeaders($this->auth)
        ->postJson('/api/v1/taurus/ingest', taurusReport())
        ->assertStatus(202)
        ->assertJsonPath('data.accepted', 1);

    $agent = TaurusAgent::withoutGlobalScopes()->sole();
    expect($agent->tenant_id)->toBe($this->tenant->id)
        ->and($agent->taurus_workspace_id)->toBe($this->workspace->id)
        ->and($agent->floor)->toBe('ops')
        ->and($agent->name)->toBe('Ledger')
        ->and($agent->zone)->toBe('Finance')
        ->and($agent->status->value)->toBe('working')
        ->and($agent->current_task)->toBe('Send October invoices')
        ->and($agent->progress)->toBe(0.4)
        ->and($agent->last_seen_at)->not->toBeNull();

    $task = TaurusTask::withoutGlobalScopes()->sole();
    expect($task->status->value)->toBe('running')
        ->and($task->started_at)->not->toBeNull()
        ->and($task->tenant_id)->toBe($this->tenant->id);

    $messages = TaurusEvent::withoutGlobalScopes()->orderBy('id')->pluck('message', 'kind');
    expect($messages['status'])->toBe('is working')
        ->and($messages['task'])->toBe('started: Send October invoices')
        ->and($messages['message'])->toBe('Drafting 12 invoices')
        ->and($messages['spend'])->toContain('openai');

    // Stored exactly as reported: 0.1 USD is 100,000 micros, never a float.
    $spend = TaurusSpendEvent::withoutGlobalScopes()->sole();
    expect($spend->amount_micros)->toBe(100_000)
        ->and($spend->currency)->toBe('USD')
        ->and($spend->units)->toBe(1200.0)
        ->and($spend->agent_id)->toBe($agent->id);
});

it('upserts idempotently — a repeated report adds no agents, tasks or status lines', function (): void {
    $report = taurusReport();
    unset($report['events'][0]['spend'], $report['events'][0]['message']);

    $this->withHeaders($this->auth)->postJson('/api/v1/taurus/ingest', $report)->assertStatus(202);
    $this->withHeaders($this->auth)->postJson('/api/v1/taurus/ingest', $report)->assertStatus(202);

    expect(TaurusAgent::withoutGlobalScopes()->count())->toBe(1)
        ->and(TaurusTask::withoutGlobalScopes()->count())->toBe(1)
        ->and(TaurusEvent::withoutGlobalScopes()->count())->toBe(2); // one status + one task line, not four

    // Finishing the task is a change, so it is recorded once.
    $done = taurusReport(['events' => [['task' => ['status' => 'done']]]]);
    unset($done['events'][0]['spend'], $done['events'][0]['message'], $done['events'][0]['status'], $done['events'][0]['task']['progress']);
    $this->withHeaders($this->auth)->postJson('/api/v1/taurus/ingest', $done)->assertStatus(202);

    $task = TaurusTask::withoutGlobalScopes()->sole();
    expect($task->status->value)->toBe('done')
        ->and($task->finished_at)->not->toBeNull()
        ->and($task->progress)->toBe(1.0)
        ->and(TaurusEvent::withoutGlobalScopes()->where('message', 'completed: Send October invoices')->count())->toBe(1);

    // The agent no longer advertises a finished task.
    expect(TaurusAgent::withoutGlobalScopes()->sole()->current_task)->toBeNull();
});

it('keeps floors apart and defaults a missing name to the slug', function (): void {
    $this->withHeaders($this->auth)->postJson('/api/v1/taurus/ingest', [
        'floor' => 'recruitment',
        'events' => [['agent' => ['slug' => 'ledger-bot']]],
    ])->assertStatus(202);
    $this->withHeaders($this->auth)->postJson('/api/v1/taurus/ingest', [
        'events' => [['agent' => ['slug' => 'ledger-bot']]],
    ])->assertStatus(202);

    expect(TaurusAgent::withoutGlobalScopes()->orderBy('floor')->pluck('floor')->all())->toBe(['ops', 'recruitment'])
        ->and(TaurusAgent::withoutGlobalScopes()->first()->name)->toBe('ledger-bot');
});

it('turns an approval request into a needs_approval task and a waiting agent', function (): void {
    $this->withHeaders($this->auth)->postJson('/api/v1/taurus/ingest', [
        'events' => [[
            'agent' => ['slug' => 'outreach'],
            'task' => ['ref' => 'mail-7', 'title' => 'Email 40 leads', 'approval' => ['action' => 'Send 40 cold emails', 'risk' => 'high']],
        ]],
    ])->assertStatus(202);

    $task = TaurusTask::withoutGlobalScopes()->sole();
    expect($task->status->value)->toBe('needs_approval')
        ->and($task->approval_action)->toBe('Send 40 cold emails')
        ->and($task->risk)->toBe('high');

    expect(TaurusAgent::withoutGlobalScopes()->sole()->status->value)->toBe('needs')
        ->and(TaurusEvent::withoutGlobalScopes()->where('kind', 'task')->value('message'))->toBe('needs your approval: Send 40 cold emails');
});

it('records units-only spend without inventing an amount', function (): void {
    $this->withHeaders($this->auth)->postJson('/api/v1/taurus/ingest', [
        'events' => [['agent' => ['slug' => 'scraper'], 'spend' => ['source' => 'apify', 'units' => 3, 'unit_label' => 'runs']]],
    ])->assertStatus(202);

    $spend = TaurusSpendEvent::withoutGlobalScopes()->sole();
    expect($spend->amount_micros)->toBeNull()
        ->and($spend->currency)->toBeNull()
        ->and($spend->units)->toBe(3.0);
});

it('validates the report shape', function (array $body): void {
    $this->withHeaders($this->auth)->postJson('/api/v1/taurus/ingest', $body)->assertUnprocessable();
})->with([
    'no events' => [['events' => []]],
    'unknown floor' => [['floor' => 'moon', 'events' => [['agent' => ['slug' => 'a']]]]],
    'bad slug' => [['events' => [['agent' => ['slug' => 'has spaces']]]]],
    'bot cannot report rejected' => [['events' => [['agent' => ['slug' => 'a'], 'task' => ['ref' => 'x', 'status' => 'rejected']]]]],
    'progress above 1' => [['events' => [['agent' => ['slug' => 'a'], 'task' => ['ref' => 'x', 'progress' => 1.5]]]]],
    'negative spend' => [['events' => [['agent' => ['slug' => 'a'], 'spend' => ['source' => 's', 'amount' => -1]]]]],
    'too many events' => [['events' => array_fill(0, 101, ['agent' => ['slug' => 'a']])]],
]);

it('rejects the decisions poll without a token', function (): void {
    $this->getJson('/api/v1/taurus/decisions')->assertUnauthorized();
});

it('converts reported amounts to micros exactly', function (mixed $amount, int $micros): void {
    expect(IngestEvents::toMicros($amount))->toBe($micros);
})->with([
    ['0.1', 100_000],
    ['12.345678', 12_345_678],
    ['1.0000005', 1_000_001],
    [3, 3_000_000],
    [0.25, 250_000],
    ['1500000', 1_500_000_000_000],
]);
