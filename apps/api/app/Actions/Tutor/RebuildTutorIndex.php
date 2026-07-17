<?php

declare(strict_types=1);

namespace App\Actions\Tutor;

use App\Enums\NoteStatus;
use App\Models\CodingLab;
use App\Models\Course;
use App\Models\KnowledgeDocument;
use App\Models\LessonNote;
use App\Models\Program;
use App\Models\Tenant;
use App\Support\Tenancy\TenantContext;

/**
 * (Re)builds the AI Tutor knowledge base from existing content (PRD §6.4): course &
 * program descriptions + coding-lab instructions become `knowledge_documents`
 * (upserted by source), then chunked. Trainer-authored `manual` docs are left
 * untouched. Mirrors the per-tenant loop of the fee/scoring batch commands.
 *
 * @phpstan-type IndexSummary array{documents: int, chunks: int}
 */
final readonly class RebuildTutorIndex
{
    public function __construct(private IngestKnowledgeDocument $ingest) {}

    /**
     * @return IndexSummary
     */
    public function handle(?Tenant $only = null): array
    {
        $summary = ['documents' => 0, 'chunks' => 0];

        $tenants = $only !== null ? collect([$only]) : Tenant::query()->get();

        foreach ($tenants as $tenant) {
            app(TenantContext::class)->run($tenant, function () use (&$summary): void {
                foreach ($this->sources() as $source) {
                    if (trim($source['body']) === '') {
                        continue;
                    }

                    $document = KnowledgeDocument::query()->updateOrCreate(
                        ['source_type' => $source['source_type'], 'source_id' => $source['source_id']],
                        [
                            'title' => $source['title'],
                            'body' => $source['body'],
                            'course_id' => $source['course_id'],
                            'lesson_id' => $source['lesson_id'],
                            'is_active' => true,
                        ],
                    );

                    $summary['documents']++;
                    $summary['chunks'] += $this->ingest->handle($document);
                }
            });
        }

        return $summary;
    }

    /**
     * The retrievable content in the current tenant, normalized to document rows.
     *
     * @return list<array{source_type: string, source_id: int, title: string, body: string, course_id: ?int, lesson_id: ?int}>
     */
    private function sources(): array
    {
        $rows = [];

        foreach (Program::query()->get(['id', 'name', 'description']) as $program) {
            $rows[] = [
                'source_type' => 'program', 'source_id' => $program->id,
                'title' => $program->name, 'body' => (string) $program->description,
                'course_id' => null, 'lesson_id' => null,
            ];
        }

        foreach (Course::query()->get(['id', 'name', 'tagline', 'description']) as $course) {
            $rows[] = [
                'source_type' => 'course', 'source_id' => $course->id,
                'title' => $course->name,
                'body' => trim((string) $course->tagline."\n\n".(string) $course->description),
                'course_id' => $course->id, 'lesson_id' => null,
            ];
        }

        $labs = CodingLab::query()->with('lesson.topic.module')->get();
        foreach ($labs as $lab) {
            $courseId = $lab->lesson?->topic?->module?->course_id;
            $rows[] = [
                'source_type' => 'coding_lab', 'source_id' => $lab->id,
                'title' => 'Lab: '.($lab->lesson?->title ?? 'Coding lab'),
                'body' => (string) $lab->instructions,
                'course_id' => $courseId, 'lesson_id' => $lab->lesson_id,
            ];
        }

        // Approved class transcripts only (PRD §6.10) — drafts must never be citable.
        $transcripts = LessonNote::query()
            ->where('status', NoteStatus::Approved->value)
            ->with('lesson.topic.module')
            ->get();
        foreach ($transcripts as $note) {
            $rows[] = [
                'source_type' => 'transcript', 'source_id' => $note->id,
                'title' => 'Transcript: '.($note->lesson?->title ?? 'Lesson'),
                'body' => $note->transcript,
                'course_id' => $note->lesson?->topic?->module?->course_id,
                'lesson_id' => $note->lesson_id,
            ];
        }

        return $rows;
    }
}
