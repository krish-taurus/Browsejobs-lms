<?php

declare(strict_types=1);

use App\Enums\EmployerJobStatus;
use App\Models\EmployerJob;
use App\Models\EmployerMember;
use App\Models\EmployerWhatsappCode;
use App\Models\EmployerWhatsappLink;
use App\Models\EmployerWhatsappMessage;
use App\Models\EmployerWorkspace;
use App\Models\Tenant;
use App\Models\User;
use App\Services\AI\AiClient;
use App\Services\AI\FakeAiClient;
use App\Support\WhatsApp\FakeWhatsAppClient;
use App\Support\WhatsApp\WhatsAppClient;
use Illuminate\Support\Facades\Artisan;
use Laravel\Sanctum\Sanctum;

beforeEach(function (): void {
    config(['services.whatsapp.display_number' => '+91 79756 66665', 'app.frontend_url' => 'https://browsejobs.ai']);

    $this->tenant = Tenant::factory()->create();
    $this->recruiter = User::factory()->for($this->tenant)->create(['user_type' => 'employer']);
    $this->workspace = EmployerWorkspace::factory()->for($this->tenant)->create(['name' => 'Incture']);
    EmployerMember::factory()->for($this->tenant)->create([
        'employer_workspace_id' => $this->workspace->id,
        'user_id' => $this->recruiter->id,
    ]);

    $this->wa = new FakeWhatsAppClient;
    app()->instance(WhatsAppClient::class, $this->wa);
    $this->ai = new FakeAiClient;
    app()->instance(AiClient::class, $this->ai);
});

/** Send one inbound message the way the CRM does and return what the bridge printed. */
function inbound(string $from, string $text, ?string $wamid = null): string
{
    Artisan::call('employer:whatsapp-inbound', array_filter([
        'from' => $from,
        '--text' => $text,
        '--id' => $wamid ?? 'wamid.'.uniqid(),
    ]));

    return trim(Artisan::output());
}

function linkPhone(object $test, string $phone = '919845011111'): EmployerWhatsappLink
{
    Sanctum::actingAs($test->recruiter);
    $code = $test->getJson("/api/v1/employer/workspaces/{$test->workspace->id}/whatsapp")->json('data.code');
    expect(inbound($phone, "JOIN {$code}"))->toBe('queued');

    return EmployerWhatsappLink::query()->where('phone', $phone)->firstOrFail();
}

it('gives an owner or recruiter a QR that opens WhatsApp with their JOIN code', function (): void {
    Sanctum::actingAs($this->recruiter);

    $data = $this->getJson("/api/v1/employer/workspaces/{$this->workspace->id}/whatsapp")->assertOk()->json('data');

    expect($data['code'])->toMatch('/^BJ-[A-HJ-NP-Z2-9]{6}$/')
        ->and($data['chat_url'])->toBe('https://wa.me/917975666665?text=JOIN%20'.$data['code'])
        ->and($data['qr_svg'])->toContain('<svg');

    // Same code on the next visit; a new one only when asked for.
    expect($this->getJson("/api/v1/employer/workspaces/{$this->workspace->id}/whatsapp")->json('data.code'))->toBe($data['code']);
    $fresh = $this->postJson("/api/v1/employer/workspaces/{$this->workspace->id}/whatsapp/regenerate")->assertOk()->json('data.code');
    expect($fresh)->not->toBe($data['code'])
        ->and(EmployerWhatsappCode::query()->where('code', $data['code'])->exists())->toBeFalse();
});

it('does not give a hiring manager a code', function (): void {
    $manager = User::factory()->for($this->tenant)->create(['user_type' => 'employer']);
    EmployerMember::factory()->for($this->tenant)->hiringManager()->create([
        'employer_workspace_id' => $this->workspace->id, 'user_id' => $manager->id,
    ]);
    Sanctum::actingAs($manager);

    $this->getJson("/api/v1/employer/workspaces/{$this->workspace->id}/whatsapp")->assertOk()->assertJsonPath('data.code', null);
    $this->postJson("/api/v1/employer/workspaces/{$this->workspace->id}/whatsapp/regenerate")->assertForbidden();
});

it('links a phone with JOIN and leaves everyone else to the CRM', function (): void {
    $link = linkPhone($this);

    expect($link->employer_workspace_id)->toBe($this->workspace->id)
        ->and($link->user_id)->toBe($this->recruiter->id)
        ->and($this->wa->sent[0]['to'])->toBe('919845011111')
        ->and($this->wa->sent[0]['body'])->toContain('Connected to *Incture*');

    // An unknown number saying hello is not a hiring chat — the CRM keeps it.
    expect(inbound('919900000000', 'hello'))->toBe('not_employer')
        ->and(EmployerWhatsappMessage::query()->where('phone', '919900000000')->exists())->toBeFalse();

    // Meta retrying the same message never produces a second reply.
    expect(inbound('919845011111', 'hi', 'wamid.same'))->toBe('queued')
        ->and(inbound('919845011111', 'hi', 'wamid.same'))->toBe('duplicate');
});

it('turns a message into a draft, applies a change, and posts only on YES', function (): void {
    linkPhone($this);
    $this->ai->replies = [
        json_encode(['title' => 'Data Engineer', 'experience_min_years' => 3, 'experience_max_years' => 5, 'locations' => ['Bengaluru'], 'openings' => 2, 'remote' => false]),
        json_encode(['description' => 'You will build our ingestion pipelines on AWS.', 'skills' => ['Python', 'SQL', 'Airflow']]),
    ];

    inbound('919845011111', 'We need 2 data engineers in Bengaluru, 3-5 years, python sql airflow');

    $link = EmployerWhatsappLink::query()->firstOrFail();
    $summary = end($this->wa->sent)['body'];
    expect($link->state)->toBe('confirming')
        ->and($summary)->toContain('*Role:* Data Engineer')
        ->and($summary)->toContain('3–5 years')
        ->and($summary)->toContain('Reply *YES*')
        ->and(EmployerJob::query()->count())->toBe(0); // nothing goes live without YES

    $this->ai->replies = [
        json_encode(['title' => '', 'experience_min_years' => 4, 'experience_max_years' => 7, 'locations' => [], 'openings' => null, 'remote' => false]),
        json_encode(['description' => 'You will build our ingestion pipelines on AWS with Kafka.', 'skills' => ['Python', 'SQL', 'Airflow', 'Kafka'], 'summary' => 'Added Kafka.']),
    ];
    inbound('919845011111', 'make it 4-7 years and add kafka');

    expect(end($this->wa->sent)['body'])->toContain('4–7 years')->toContain('kafka')
        ->and($link->fresh()->draft['experience_min_years'])->toBe(4);

    inbound('919845011111', 'YES');

    $job = EmployerJob::query()->firstOrFail();
    expect($job->status)->toBe(EmployerJobStatus::Published)
        ->and($job->title)->toBe('Data Engineer')
        ->and($job->experience_min_years)->toBe(4)
        ->and($job->openings)->toBe(2)
        ->and($job->employer_workspace_id)->toBe($this->workspace->id)
        ->and($job->created_by_id)->toBe($this->recruiter->id)
        ->and(end($this->wa->sent)['body'])->toContain('is live')->toContain("/employer/jobs/{$job->id}?tab=shortlist")
        ->and($link->fresh()->state)->toBe('idle');
});

it('discards a draft on CANCEL', function (): void {
    $link = linkPhone($this);
    $link->update(['state' => 'confirming', 'draft' => ['title' => 'QA Engineer', 'description' => 'x', 'skills' => []]]);

    inbound('919845011111', 'cancel');

    expect($link->fresh()->state)->toBe('idle')
        ->and($link->fresh()->draft)->toBeNull()
        ->and(end($this->wa->sent)['body'])->toContain('Discarded')
        ->and(EmployerJob::query()->count())->toBe(0);
});

it('stops a phone posting once its member loses recruiter access, and refuses expired codes', function (): void {
    linkPhone($this);
    EmployerMember::query()->where('user_id', $this->recruiter->id)->update(['role' => 'hiring_manager']);

    inbound('919845011111', 'we need a java developer');
    expect(end($this->wa->sent)['body'])->toContain('can no longer post jobs')
        ->and(EmployerJob::query()->count())->toBe(0);

    EmployerWhatsappCode::query()->update(['expires_at' => now()->subDay()]);
    $code = EmployerWhatsappCode::query()->value('code');
    inbound('919800000002', "JOIN {$code}");
    expect(end($this->wa->sent)['body'])->toContain('not valid any more')
        ->and(EmployerWhatsappLink::query()->where('phone', '919800000002')->exists())->toBeFalse();
});

it('keeps a workspace\'s WhatsApp links and chats away from everyone else', function (): void {
    $link = linkPhone($this);

    $stranger = User::factory()->for($this->tenant)->create(['user_type' => 'employer']);
    $other = EmployerWorkspace::factory()->for($this->tenant)->create();
    EmployerMember::factory()->for($this->tenant)->create(['employer_workspace_id' => $other->id, 'user_id' => $stranger->id]);
    Sanctum::actingAs($stranger);
    $this->getJson("/api/v1/employer/workspaces/{$this->workspace->id}/whatsapp")->assertForbidden();
    $this->deleteJson("/api/v1/employer/workspaces/{$other->id}/whatsapp/links/{$link->id}")->assertNotFound();
    expect($this->getJson("/api/v1/employer/workspaces/{$other->id}/whatsapp")->json('data.links'))->toBe([]);

    $outsider = User::factory()->for(Tenant::factory()->create())->create(['user_type' => 'employer']);
    Sanctum::actingAs($outsider);
    $this->getJson("/api/v1/employer/workspaces/{$this->workspace->id}/whatsapp")->assertNotFound();

    // The owner can disconnect a phone; it is then just a stranger to the hiring chat.
    Sanctum::actingAs($this->recruiter);
    $this->deleteJson("/api/v1/employer/workspaces/{$this->workspace->id}/whatsapp/links/{$link->id}")->assertOk();
    expect(inbound('919845011111', 'we need a java developer'))->toBe('not_employer');
});
