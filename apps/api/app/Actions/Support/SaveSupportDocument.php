<?php

declare(strict_types=1);

namespace App\Actions\Support;

use App\Actions\Tutor\IngestKnowledgeDocument;
use App\Models\KnowledgeDocument;
use App\Models\User;
use App\Support\Audit\AuditLogger;
use App\Support\Tenancy\TenantContext;

/**
 * Creates or updates a support policy document and re-chunks it (PRD §6.13).
 *
 * The write and the re-ingest are one action on purpose. A document whose body changed
 * but whose chunks did not is worse than no document: retrieval would serve the old
 * policy while the admin screen showed the new one, and nobody would know which the
 * student was told. `IngestKnowledgeDocument` replaces the document's chunks, so this
 * is safe to run on every save.
 *
 * Audited — support policy is what deflection tells students about their money, so who
 * changed it and when is not optional.
 */
final readonly class SaveSupportDocument
{
    public function __construct(
        private IngestKnowledgeDocument $ingest,
        private AuditLogger $audit,
    ) {}

    /**
     * @param  array{title: string, category: string, body: string, is_active?: bool}  $data
     */
    public function handle(array $data, ?KnowledgeDocument $document = null, ?User $actor = null): KnowledgeDocument
    {
        return app(TenantContext::class)->run($actor?->tenant ?? app(TenantContext::class)->get(), function () use ($data, $document, $actor): KnowledgeDocument {
            $attributes = [
                'title' => $data['title'],
                'category' => $data['category'],
                'body' => $data['body'],
                'is_active' => $data['is_active'] ?? true,
                'source_type' => 'support',
                'authored_by' => $actor?->id,
            ];

            if ($document === null) {
                $document = KnowledgeDocument::query()->create($attributes);
                $action = 'support_document.created';
            } else {
                $document->update($attributes);
                $action = 'support_document.updated';
            }

            $chunks = $this->ingest->handle($document);

            $this->audit->log($action, $document, [
                'title' => $document->title,
                'category' => $document->category,
                'is_active' => $document->is_active,
                'chunks' => $chunks,
            ], $actor);

            return $document->fresh();
        });
    }
}
