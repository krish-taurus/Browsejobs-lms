<?php

declare(strict_types=1);

use App\Enums\VerificationKind;
use App\Enums\VerificationStatus;
use App\Jobs\AssessCandidateDocument;
use App\Models\CandidateDocument;
use App\Models\Tenant;
use App\Models\User;
use App\Services\AI\AiClient;
use App\Services\AI\AiGateway;
use App\Services\AI\FakeAiClient;
use App\Support\Files\DocxExtractor;
use App\Support\Verification\Providers\AiDocumentProvider;
use App\Support\Verification\Providers\DigiLockerProvider;
use App\Support\Verification\Providers\EpfoProvider;
use App\Support\Verification\VerificationGateway;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;

/**
 * PRD-E F9 — the BGV providers BrowseJobs runs on a candidate's behalf, and
 * the per-document AI triage behind the documents check.
 *
 * The assertions that matter are about what these never do: never verify
 * without a source, never fail a candidate for our own outage, and never let
 * a model grant a badge.
 */
beforeEach(function (): void {
    $this->tenant = Tenant::factory()->create();
    $this->candidate = User::factory()->for($this->tenant)->create(['user_type' => 'student']);
    $this->fake = new FakeAiClient;
    app()->instance(AiClient::class, $this->fake);
});

/* ------------------------------ DigiLocker ------------------------------ */

it('waits for a human rather than failing a candidate when DigiLocker is not configured', function (): void {
    config(['services.digilocker.base_url' => '', 'services.digilocker.client_id' => '', 'services.digilocker.client_secret' => '']);
    Http::fake();

    $outcome = (new DigiLockerProvider)->start($this->candidate, VerificationKind::Identity, []);

    // A missing credential is our problem. Recording it as `failed` would put
    // a black mark on a person for an outage on our side.
    expect($outcome->status)->toBe(VerificationStatus::Pending);
    Http::assertNothingSent();
});

it('records a DigiLocker match with its provenance and nothing else', function (): void {
    config([
        'services.digilocker.base_url' => 'https://digilocker.test',
        'services.digilocker.client_id' => 'id-from-admin-settings',
        'services.digilocker.client_secret' => 'secret-from-admin-settings',
    ]);

    Http::fake(['*' => Http::response([
        'status' => 'verified',
        'reference' => 'DL-123',
        'issuer' => 'UIDAI',
        'document_type' => 'aadhaar',
        'matched_on' => 'name+dob',
        // A real aggregator returns the document too. It must not survive
        // into our evidence — the employer view returns outcomes only.
        'document' => ['number' => '1234-5678-9012', 'image' => 'base64…'],
    ])]);

    $outcome = (new DigiLockerProvider)->start($this->candidate, VerificationKind::Identity, ['reference' => 'x']);

    expect($outcome->status)->toBe(VerificationStatus::Verified)
        ->and($outcome->providerRef)->toBe('DL-123')
        ->and($outcome->evidence)->toBe([
            'issuer' => 'UIDAI',
            'document_type' => 'aadhaar',
            'matched_on' => 'name+dob',
        ])
        ->and($outcome->evidence)->not->toHaveKey('document');
});

it('fails a check only when DigiLocker itself says there is no match', function (): void {
    config([
        'services.digilocker.base_url' => 'https://digilocker.test',
        'services.digilocker.client_id' => 'id',
        'services.digilocker.client_secret' => 'secret',
    ]);

    Http::fake(['*' => Http::response(['status' => 'no_match', 'reason' => 'No issued degree for these details.'])]);

    $outcome = (new DigiLockerProvider)->start($this->candidate, VerificationKind::Education, []);

    expect($outcome->status)->toBe(VerificationStatus::Failed)
        ->and($outcome->failureReason)->toBe('No issued degree for these details.');
});

it('stays pending when DigiLocker errors, so an outage is never a candidate failure', function (): void {
    config([
        'services.digilocker.base_url' => 'https://digilocker.test',
        'services.digilocker.client_id' => 'id',
        'services.digilocker.client_secret' => 'secret',
    ]);

    Http::fake(['*' => Http::response([], 503)]);

    expect((new DigiLockerProvider)->start($this->candidate, VerificationKind::Identity, [])->status)
        ->toBe(VerificationStatus::Pending);
});

it('will not service a kind it has no source for', function (): void {
    expect((new DigiLockerProvider)->supports(VerificationKind::Employment))->toBeFalse()
        ->and((new EpfoProvider)->supports(VerificationKind::Identity))->toBeFalse()
        ->and((new AiDocumentProvider)->supports(VerificationKind::Identity))->toBeFalse();
});

/* --------------------------------- EPFO --------------------------------- */

it('keeps only the shape of an EPFO match, never the record behind it', function (): void {
    config(['services.epfo.base_url' => 'https://bgv.test', 'services.epfo.api_key' => 'key']);

    Http::fake(['*' => Http::response([
        'status' => 'verified',
        'reference' => 'PF-9',
        'employers_matched' => 3,
        'months_covered' => 48,
        'uan' => '100200300400',
        'salary' => 1800000,
    ])]);

    $outcome = (new EpfoProvider)->start($this->candidate, VerificationKind::Employment, []);

    expect($outcome->status)->toBe(VerificationStatus::Verified)
        ->and($outcome->evidence)->toBe(['employers_matched' => 3, 'months_covered' => 48])
        ->and($outcome->evidence)->not->toHaveKey('uan')
        ->and($outcome->evidence)->not->toHaveKey('salary');
});

/* ---------------------- Documents: per-file AI triage --------------------- */

it('queues one assessment per uploaded document', function (): void {
    Queue::fake();
    Storage::fake('local');
    config(['filesystems.default' => 'local']);

    Sanctum::actingAs($this->candidate);

    $this->postJson('/api/v1/me/verification-documents', [
        'kind' => 'payslip',
        'file' => UploadedFile::fake()->createWithContent('march.txt', 'Payslip March 2024. Net 84,000.'),
    ])->assertCreated();

    $this->postJson('/api/v1/me/verification-documents', [
        'kind' => 'offer_letter',
        'file' => UploadedFile::fake()->createWithContent('offer.txt', 'Offer of employment as Data Engineer.'),
    ])->assertCreated();

    // One job per file: a slow or failed assessment on one never blocks the
    // rest, and the reviewer gets a verdict per document rather than a folder.
    Queue::assertPushed(AssessCandidateDocument::class, 2);
});

it('assesses a readable document and stores the verdict as advice, never a decision', function (): void {
    Storage::fake('local');
    config(['filesystems.default' => 'local']);

    $this->fake->reply = json_encode([
        'readable' => true,
        'document_matches_kind' => true,
        'name_on_document' => 'Sneha Nair',
        'name_matches_profile' => true,
        'employer_or_issuer' => 'Zeta Systems',
        'period_stated' => 'March 2024',
        'observations' => ['Net pay 84,000 for March 2024.'],
        'concerns' => [],
        'recommendation' => 'looks_consistent',
        'summary' => 'Reads as a normal payslip for March 2024; nothing inconsistent.',
    ], JSON_THROW_ON_ERROR);

    Storage::disk('local')->put('verification/1/march.txt', 'Payslip March 2024. Net 84,000.');

    $document = CandidateDocument::query()->create([
        'tenant_id' => $this->tenant->id,
        'user_id' => $this->candidate->id,
        'kind' => 'payslip',
        'path' => 'verification/1/march.txt',
        'original_name' => 'march.txt',
    ]);

    app(AssessCandidateDocument::class, ['documentId' => $document->id])
        ->handle(app(AiGateway::class), app(DocxExtractor::class));

    $document->refresh();

    expect($document->extract_status)->toBe(CandidateDocument::EXTRACT_DONE)
        ->and($document->ai_verdict['recommendation'])->toBe('looks_consistent')
        // Stored, not just displayed: whatever the model wrote, this was
        // never a confirmation that the document is genuine.
        ->and($document->ai_verdict['is_advice_only'])->toBeTrue()
        // And the check itself is untouched — a model does not settle it.
        ->and($document->review_status)->toBe(CandidateDocument::REVIEW_PENDING);
});

it('marks a document it cannot read as unreadable instead of assessing a filename', function (): void {
    Storage::fake('local');
    config(['filesystems.default' => 'local']);

    // There is no PDF extractor and no OCR in this stack. Running the model
    // on the filename would produce a verdict indistinguishable from a real
    // one, so the file goes to a human instead.
    Storage::disk('local')->put('verification/1/offer.pdf', '%PDF-1.4 binary…');

    $document = CandidateDocument::query()->create([
        'tenant_id' => $this->tenant->id,
        'user_id' => $this->candidate->id,
        'kind' => 'offer_letter',
        'path' => 'verification/1/offer.pdf',
        'original_name' => 'offer.pdf',
    ]);

    app(AssessCandidateDocument::class, ['documentId' => $document->id])
        ->handle(app(AiGateway::class), app(DocxExtractor::class));

    $document->refresh();

    expect($document->extract_status)->toBe(CandidateDocument::EXTRACT_UNREADABLE)
        ->and($document->ai_verdict)->toBeNull()
        ->and($document->assessmentSummary())->toContain('No AI assessment was made');
});

it('never lets the AI provider settle the documents check', function (): void {
    Queue::fake();
    config(['verification.routing.documents' => 'ai_documents']);

    $check = app(VerificationGateway::class)->submit($this->candidate, VerificationKind::Documents, []);

    expect($check->effectiveStatus())->toBe(VerificationStatus::Pending)
        ->and($check->provider)->toBe('ai_documents')
        ->and($check->verified_at)->toBeNull();
});

/* ------------------------------ Ownership ------------------------------- */

it('keeps one candidate out of another candidate documents', function (): void {
    Storage::fake('local');
    config(['filesystems.default' => 'local']);

    $document = CandidateDocument::query()->create([
        'tenant_id' => $this->tenant->id,
        'user_id' => $this->candidate->id,
        'kind' => 'payslip',
        'path' => 'verification/1/a.txt',
        'original_name' => 'a.txt',
    ]);

    $other = User::factory()->for($this->tenant)->create(['user_type' => 'student']);
    Sanctum::actingAs($other);

    $this->deleteJson("/api/v1/me/verification-documents/{$document->id}")->assertNotFound();
    $this->getJson('/api/v1/me/verification-documents')->assertOk()->assertJsonCount(0, 'data');

    expect(CandidateDocument::withoutGlobalScopes()->find($document->id))->not->toBeNull();
});

it('refuses to delete a document a reviewer has already ruled on', function (): void {
    Storage::fake('local');
    config(['filesystems.default' => 'local']);

    $document = CandidateDocument::query()->create([
        'tenant_id' => $this->tenant->id,
        'user_id' => $this->candidate->id,
        'kind' => 'payslip',
        'path' => 'verification/1/a.txt',
        'original_name' => 'a.txt',
        'review_status' => CandidateDocument::REVIEW_ACCEPTED,
    ]);

    Sanctum::actingAs($this->candidate);

    $this->deleteJson("/api/v1/me/verification-documents/{$document->id}")->assertStatus(422);

    expect(CandidateDocument::withoutGlobalScopes()->find($document->id))->not->toBeNull();
});

it('never shows a candidate the AI assessment written for the reviewer', function (): void {
    Storage::fake('local');
    config(['filesystems.default' => 'local']);

    CandidateDocument::query()->create([
        'tenant_id' => $this->tenant->id,
        'user_id' => $this->candidate->id,
        'kind' => 'payslip',
        'path' => 'verification/1/a.txt',
        'original_name' => 'a.txt',
        'ai_verdict' => ['concerns' => ['Dates do not line up.'], 'summary' => 'Check the period.'],
        'assessed_at' => now(),
    ]);

    Sanctum::actingAs($this->candidate);

    $response = $this->getJson('/api/v1/me/verification-documents')->assertOk();

    expect(json_encode($response->json()))->not->toContain('Dates do not line up')
        ->and(json_encode($response->json()))->not->toContain('Check the period');
});

/* --------------------------- Misconfiguration --------------------------- */

it('falls back to manual review when a kind is routed to a provider that does not exist', function (): void {
    // Reachable: routing is admin-editable. Throwing here would surface as a
    // 500 on a candidate pressing submit, nowhere near the setting at fault.
    config(['verification.routing.identity' => 'not-a-provider']);

    $check = app(VerificationGateway::class)->submit($this->candidate, VerificationKind::Identity, []);

    expect($check->effectiveStatus())->toBe(VerificationStatus::Pending)
        ->and($check->provider)->toBe('manual');
});

it('falls back when a kind is routed to a provider that cannot service it', function (): void {
    config(['verification.routing.employment' => 'digilocker']);

    $check = app(VerificationGateway::class)->submit($this->candidate, VerificationKind::Employment, []);

    expect($check->provider)->toBe('manual');
});
