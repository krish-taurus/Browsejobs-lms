<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Tutor\IngestKnowledgeDocument;
use App\Models\Course;
use App\Models\KnowledgeDocument;
use App\Models\Tenant;
use App\Support\Tenancy\TenantContext;
use Illuminate\Console\Command;
use Throwable;

/**
 * CRUD for AI Tutor knowledge documents, driven from the CRM (the founder never
 * opens the LMS admin panel). Every write re-chunks the document through
 * {@see IngestKnowledgeDocument}: retrieval reads `knowledge_chunks`, never the
 * body, so a row saved without chunking is invisible to the tutor.
 *
 * Bodies arrive via --body-file because course material is far too large for argv.
 * `list` and `save` print JSON so the CRM can parse the result.
 */
final class ManageTutorKnowledge extends Command
{
    protected $signature = 'tutor:knowledge
        {action : list, save or delete}
        {--id= : document id — save updates it, delete removes it}
        {--title= : document title}
        {--course= : course id or slug; omit for a general document}
        {--category= : free-text grouping label}
        {--body-file= : path to a UTF-8 text file holding the body}
        {--active=1 : 1 or 0}
        {--tenant=1 : tenant id}';

    protected $description = 'List, save or delete AI Tutor knowledge documents';

    public function handle(IngestKnowledgeDocument $ingest): int
    {
        $tenant = Tenant::query()->find((int) $this->option('tenant'));

        if ($tenant === null) {
            $this->error('Tenant not found.');

            return self::FAILURE;
        }

        return app(TenantContext::class)->run($tenant, function () use ($ingest, $tenant): int {
            try {
                return match ($this->argument('action')) {
                    'list' => $this->listDocuments(),
                    'save' => $this->save($ingest, $tenant),
                    'delete' => $this->deleteDocument(),
                    default => $this->unknownAction(),
                };
            } catch (Throwable $e) {
                $this->error($e->getMessage());

                return self::FAILURE;
            }
        });
    }

    private function listDocuments(): int
    {
        $rows = KnowledgeDocument::query()
            ->withCount('chunks')
            ->with('course:id,name,slug')
            ->orderBy('title')
            ->get()
            ->map(fn (KnowledgeDocument $d): array => [
                'id' => $d->id,
                'title' => $d->title,
                'category' => $d->category,
                'course_id' => $d->course_id,
                'course_name' => $d->course?->name,
                'is_active' => (bool) $d->is_active,
                'chunks' => $d->chunks_count,
                'characters' => mb_strlen((string) $d->body),
                'updated_at' => optional($d->updated_at)->toDateTimeString(),
            ])
            ->all();

        $this->line((string) json_encode($rows, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        return self::SUCCESS;
    }

    private function save(IngestKnowledgeDocument $ingest, Tenant $tenant): int
    {
        $document = $this->option('id') !== null
            ? KnowledgeDocument::query()->find((int) $this->option('id'))
            : new KnowledgeDocument(['tenant_id' => $tenant->id, 'source_type' => 'crm']);

        if ($document === null) {
            $this->error('Document not found.');

            return self::FAILURE;
        }

        if ($this->option('title') !== null) {
            $document->title = (string) $this->option('title');
        }

        if ($document->title === null || trim($document->title) === '') {
            $this->error('A title is required.');

            return self::FAILURE;
        }

        $document->category = $this->option('category') !== null
            ? (string) $this->option('category')
            : $document->category;

        $document->course_id = $this->resolveCourseId();
        $document->is_active = (bool) (int) $this->option('active');
        $document->tenant_id ??= $tenant->id;
        $document->source_type ??= 'crm';

        if ($this->option('body-file') !== null) {
            $path = (string) $this->option('body-file');

            if (! is_readable($path)) {
                $this->error("Body file not readable: {$path}");

                return self::FAILURE;
            }

            $document->body = (string) file_get_contents($path);
        }

        if (trim((string) $document->body) === '') {
            $this->error('The document body is empty — nothing to teach from.');

            return self::FAILURE;
        }

        $document->save();

        $chunks = $ingest->handle($document);

        $this->line((string) json_encode([
            'id' => $document->id,
            'chunks' => $chunks,
            'characters' => mb_strlen($document->body),
        ], JSON_UNESCAPED_SLASHES));

        return self::SUCCESS;
    }

    private function deleteDocument(): int
    {
        $document = KnowledgeDocument::query()->find((int) $this->option('id'));

        if ($document === null) {
            $this->error('Document not found.');

            return self::FAILURE;
        }

        $document->chunks()->delete();
        $document->delete();

        $this->line((string) json_encode(['deleted' => true], JSON_UNESCAPED_SLASHES));

        return self::SUCCESS;
    }

    /** Course id or slug — null keeps the document general (no course). */
    private function resolveCourseId(): ?int
    {
        $course = $this->option('course');

        if ($course === null || trim((string) $course) === '') {
            return null;
        }

        return Course::query()
            ->when(
                is_numeric($course),
                fn ($q) => $q->whereKey((int) $course),
                fn ($q) => $q->where('slug', $course),
            )
            ->value('id');
    }

    private function unknownAction(): int
    {
        $this->error('Unknown action — use list, save or delete.');

        return self::FAILURE;
    }
}
