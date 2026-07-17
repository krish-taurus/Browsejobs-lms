<?php

declare(strict_types=1);

namespace App\Support\Tutor;

use App\Models\KnowledgeChunk;
use App\Models\TutorMessage;
use Illuminate\Support\Collection;

/**
 * Turns a tutor message's `cited_chunk_ids` into `{label, href}` links (PRD §6.4).
 * Only course pages (`/courses/{slug}`) and coding labs (`/labs/{lessonId}`) are
 * routable today, so a citation resolves to the nearest linkable ancestor; otherwise
 * it renders as a plain source label. Batch-loads to avoid N+1.
 */
final class CitationResolver
{
    /**
     * Resolve a bare chunk-id list to citations. Support deflection (PRD §6.13) cites
     * the same way the tutor does — derived from what was retrieved, never parsed out
     * of the model's answer, so a citation cannot be hallucinated.
     *
     * @param  array<int, int>  $chunkIds
     * @return list<array{label: string, href: string|null}>
     */
    public function resolve(array $chunkIds): array
    {
        if ($chunkIds === []) {
            return [];
        }

        $chunks = KnowledgeChunk::query()
            ->with('document.course:id,slug')
            ->whereIn('id', $chunkIds)
            ->get()
            ->keyBy('id');

        return $this->build($chunkIds, $chunks);
    }

    /**
     * Attach a resolved `citations` array onto each tutor message in the set.
     *
     * @param  Collection<int, TutorMessage>  $messages
     */
    public function attach(Collection $messages): void
    {
        $chunkIds = $messages
            ->flatMap(fn (TutorMessage $m) => $m->cited_chunk_ids ?? [])
            ->unique()
            ->values();

        $chunks = $chunkIds->isEmpty()
            ? collect()
            : KnowledgeChunk::query()->with('document.course:id,slug')->whereIn('id', $chunkIds)->get()->keyBy('id');

        foreach ($messages as $message) {
            $message->setAttribute('citations', $this->build($message->cited_chunk_ids ?? [], $chunks));
        }
    }

    /**
     * @param  array<int, int>  $chunkIds
     * @param  Collection<int, KnowledgeChunk>  $chunks
     * @return list<array{label: string, href: string|null}>
     */
    private function build(array $chunkIds, Collection $chunks): array
    {
        $seen = [];
        $citations = [];

        foreach ($chunkIds as $id) {
            $chunk = $chunks->get($id);
            $document = $chunk?->document;
            if ($document === null) {
                continue;
            }

            $label = $document->title;
            if (isset($seen[$label])) {
                continue;
            }
            $seen[$label] = true;

            $href = null;
            if ($document->lesson_id !== null) {
                // A transcript deep-links to the lesson's study notes; a lab to the lab.
                $href = ($document->source_type === 'transcript' ? '/notes/' : '/labs/').$document->lesson_id;
            } elseif ($document->course?->slug !== null) {
                $href = '/courses/'.$document->course->slug;
            }

            $citations[] = ['label' => $label, 'href' => $href];
        }

        return $citations;
    }
}
