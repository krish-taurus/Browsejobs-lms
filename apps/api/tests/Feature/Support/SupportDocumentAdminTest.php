<?php

declare(strict_types=1);

use App\Models\AuditLog;
use App\Models\KnowledgeChunk;
use App\Models\KnowledgeDocument;
use App\Models\Tenant;
use App\Models\User;
use App\Services\AI\AiClient;
use App\Services\AI\FakeAiClient;
use Database\Seeders\RolePermissionSeeder;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
    $this->tenant = Tenant::factory()->create();
    $this->admin = User::factory()->for($this->tenant)->create(['user_type' => 'staff']);
    $this->admin->assignRole('admin');
});

it('creates a policy document, chunks it, and audits who wrote it', function () {
    Sanctum::actingAs($this->admin);

    $this->postJson('/api/v1/admin/support-documents', [
        'title' => 'Batch transfer policy',
        'category' => 'training',
        'body' => "You can move to a later batch once.\n\nAsk before the batch you are in reaches its halfway point.",
    ])->assertCreated()->assertJsonPath('data.title', 'Batch transfer policy');

    $doc = KnowledgeDocument::withoutGlobalScopes()->where('source_type', 'support')->firstOrFail();
    expect($doc->category)->toBe('training')
        ->and($doc->authored_by)->toBe($this->admin->id)
        ->and($doc->is_active)->toBeTrue()
        // Written but unchunked would be invisible to retrieval.
        ->and(KnowledgeChunk::withoutGlobalScopes()->where('document_id', $doc->id)->count())->toBeGreaterThan(0);

    $audit = AuditLog::withoutGlobalScopes()->where('action', 'support_document.created')->first();
    expect($audit)->not->toBeNull()->and($audit->actor_id)->toBe($this->admin->id);
});

it('re-chunks on every edit so retrieval never serves the old policy', function () {
    Sanctum::actingAs($this->admin);

    $this->postJson('/api/v1/admin/support-documents', [
        'title' => 'Refund window', 'category' => 'payments', 'body' => 'The refund window is 30 days.',
    ])->assertCreated();

    $doc = KnowledgeDocument::withoutGlobalScopes()->firstOrFail();

    $this->putJson("/api/v1/admin/support-documents/{$doc->id}", [
        'title' => 'Refund window', 'category' => 'payments', 'body' => 'The refund window is 45 days.',
    ])->assertOk();

    $chunks = KnowledgeChunk::withoutGlobalScopes()->where('document_id', $doc->id)->pluck('content')->implode(' ');
    expect($chunks)->toContain('45 days')->not->toContain('30 days');

    expect(AuditLog::withoutGlobalScopes()->where('action', 'support_document.updated')->count())->toBe(1);
});

it('changes what deflection actually tells a student', function () {
    // The whole point of the screen: policy edited here reaches the student.
    $fake = new FakeAiClient;
    $fake->reply = "The window is 45 days [C1].\n\nCONFIDENCE: high";
    app()->instance(AiClient::class, $fake);

    Sanctum::actingAs($this->admin);
    $this->postJson('/api/v1/admin/support-documents', [
        'title' => 'Refund window', 'category' => 'payments',
        'body' => 'The refund window is 45 days from payment.',
    ])->assertCreated();

    $student = User::factory()->for($this->tenant)->create(['user_type' => 'student']);
    Sanctum::actingAs($student);

    $this->postJson('/api/v1/me/tickets/deflect', [
        'category' => 'payments', 'body' => 'What is the refund window?',
    ])->assertOk()->assertJsonPath('data.citations.0.label', 'Refund window');

    expect($fake->calls[0]->user)->toContain('45 days from payment');
});

it('deactivating a document takes it out of retrieval without deleting it', function () {
    $fake = new FakeAiClient;
    app()->instance(AiClient::class, $fake);

    Sanctum::actingAs($this->admin);
    $this->postJson('/api/v1/admin/support-documents', [
        'title' => 'Draft policy', 'category' => 'payments', 'body' => 'The refund window is 45 days.',
    ])->assertCreated();

    $doc = KnowledgeDocument::withoutGlobalScopes()->firstOrFail();
    $this->putJson("/api/v1/admin/support-documents/{$doc->id}", [
        'title' => 'Draft policy', 'category' => 'payments', 'body' => 'The refund window is 45 days.',
        'is_active' => false,
    ])->assertOk()->assertJsonPath('data.is_active', false);

    $student = User::factory()->for($this->tenant)->create(['user_type' => 'student']);
    Sanctum::actingAs($student);

    // An inactive policy is never quoted — the text survives for the author to finish.
    $this->postJson('/api/v1/me/tickets/deflect', [
        'category' => 'payments', 'body' => 'What is the refund window?',
    ])->assertOk()->assertJsonPath('data', null);

    expect($fake->calls)->toBeEmpty()
        ->and(KnowledgeDocument::withoutGlobalScopes()->find($doc->id))->not->toBeNull();
});

it('deletes a document and its chunks together', function () {
    Sanctum::actingAs($this->admin);
    $this->postJson('/api/v1/admin/support-documents', [
        'title' => 'Old policy', 'category' => 'other', 'body' => 'Something we no longer do.',
    ])->assertCreated();

    $doc = KnowledgeDocument::withoutGlobalScopes()->firstOrFail();
    $this->deleteJson("/api/v1/admin/support-documents/{$doc->id}")->assertOk();

    // A stranded chunk would let retrieval cite a policy that no longer exists.
    expect(KnowledgeDocument::withoutGlobalScopes()->find($doc->id))->toBeNull()
        ->and(KnowledgeChunk::withoutGlobalScopes()->where('document_id', $doc->id)->count())->toBe(0)
        ->and(AuditLog::withoutGlobalScopes()->where('action', 'support_document.deleted')->count())->toBe(1);
});

it('lists only support documents, never the tutor corpus', function () {
    withinTenant($this->tenant, function () {
        KnowledgeDocument::factory()->for($this->tenant)->create(['source_type' => 'support', 'category' => 'payments', 'title' => 'Fees']);
        KnowledgeDocument::factory()->for($this->tenant)->create(['source_type' => 'course', 'title' => 'Python course']);
        KnowledgeDocument::factory()->for($this->tenant)->create(['source_type' => 'transcript', 'title' => 'Lesson 3 transcript']);
    });

    Sanctum::actingAs($this->admin);

    $body = $this->getJson('/api/v1/admin/support-documents')->assertOk()->assertJsonCount(1, 'data');
    expect($body->json('data.0.title'))->toBe('Fees');
});

it('refuses to edit a machine-generated tutor document', function () {
    // Editing one here would be silently undone by the next tutor:reindex.
    $course = withinTenant($this->tenant, fn () => KnowledgeDocument::factory()->for($this->tenant)->create(['source_type' => 'course']));

    Sanctum::actingAs($this->admin);

    $this->putJson("/api/v1/admin/support-documents/{$course->id}", [
        'title' => 'Hijacked', 'category' => 'payments', 'body' => 'Rewritten course description.',
    ])->assertNotFound();

    $this->deleteJson("/api/v1/admin/support-documents/{$course->id}")->assertNotFound();

    expect(KnowledgeDocument::withoutGlobalScopes()->find($course->id))->not->toBeNull();
});

it('requires a category, because an uncategorised policy is unreachable', function () {
    Sanctum::actingAs($this->admin);

    // Retrieval always scopes to the ticket's category, so a null one can never match.
    $this->postJson('/api/v1/admin/support-documents', [
        'title' => 'Orphan', 'body' => 'Nobody can ever retrieve this.',
    ])->assertStatus(422);
});

it('denies a staff user without handle-tickets', function () {
    $other = User::factory()->for($this->tenant)->create(['user_type' => 'staff']);
    Sanctum::actingAs($other);

    $this->getJson('/api/v1/admin/support-documents')->assertForbidden();
    $this->postJson('/api/v1/admin/support-documents', [
        'title' => 'X', 'category' => 'other', 'body' => 'body text here',
    ])->assertForbidden();
});

it('404s another tenant support document', function () {
    $other = Tenant::factory()->create();
    $theirs = withinTenant($other, fn () => KnowledgeDocument::factory()->for($other)->create([
        'source_type' => 'support', 'category' => 'payments', 'title' => 'Their policy',
    ]));

    Sanctum::actingAs($this->admin);

    $this->putJson("/api/v1/admin/support-documents/{$theirs->id}", [
        'title' => 'Hijacked', 'category' => 'payments', 'body' => 'Rewritten from another tenant.',
    ])->assertNotFound();

    $this->getJson('/api/v1/admin/support-documents')->assertOk()->assertJsonCount(0, 'data');
    expect($theirs->fresh()->title)->toBe('Their policy');
});
