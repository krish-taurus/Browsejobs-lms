<?php

declare(strict_types=1);

namespace App\Support\Tutor;

use App\Models\KnowledgeChunk;
use Illuminate\Support\Collection;

/**
 * Lexical retrieval over `knowledge_chunks` for the AI Tutor (PRD §6.4). Deterministic
 * and portable — candidates are fetched with `LIKE` (SQLite-safe, no FULLTEXT) and
 * ranked in PHP by term frequency with length normalization. No embeddings this phase.
 */
final class KnowledgeRetriever
{
    /** Tokens shorter than this or in the stop list are ignored. */
    private const MIN_TERM_LENGTH = 3;

    private const STOPWORDS = [
        'the', 'and', 'for', 'are', 'but', 'not', 'you', 'with', 'this', 'that', 'have',
        'how', 'what', 'why', 'does', 'can', 'when', 'which', 'from', 'was', 'were', 'has',
        'about', 'into', 'your', 'they', 'their', 'them', 'then', 'than', 'will', 'would',
    ];

    /** Candidate cap before PHP ranking, to bound work on large tenants. */
    private const CANDIDATE_LIMIT = 200;

    /**
     * Split a question into unique, lowercased, meaningful terms.
     *
     * @return list<string>
     */
    public function tokenize(string $text): array
    {
        $words = preg_split('/[^\p{L}\p{N}]+/u', mb_strtolower($text), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        $terms = [];
        foreach ($words as $word) {
            if (mb_strlen($word) < self::MIN_TERM_LENGTH || in_array($word, self::STOPWORDS, true)) {
                continue;
            }
            $terms[$word] = true;
        }

        return array_keys($terms);
    }

    /**
     * Top-K chunks for the given terms, ranked by length-normalized term overlap.
     * Runs inside the caller's tenant context (BelongsToTenant scopes the query).
     *
     * @param  list<string>  $terms
     * @return Collection<int, KnowledgeChunk>
     */
    public function retrieve(array $terms, int $k, ?int $lessonId = null): Collection
    {
        if ($terms === []) {
            return collect();
        }

        $candidates = KnowledgeChunk::query()
            ->whereHas('document', fn ($q) => $q->where('is_active', true))
            ->where(function ($q) use ($terms): void {
                foreach ($terms as $term) {
                    $q->orWhere('content', 'like', '%'.$term.'%');
                }
            })
            ->with('document')
            ->limit(self::CANDIDATE_LIMIT)
            ->get();

        return $candidates
            ->map(function (KnowledgeChunk $chunk) use ($terms, $lessonId): array {
                $haystack = mb_strtolower($chunk->content);
                $overlap = 0;
                foreach ($terms as $term) {
                    $overlap += substr_count($haystack, $term);
                }

                $score = $overlap / sqrt(max(1, $chunk->term_count));
                if ($lessonId !== null && $chunk->document?->lesson_id === $lessonId) {
                    $score *= 1.5; // prefer the lab/lesson the student is actually on
                }

                return ['chunk' => $chunk, 'score' => $score];
            })
            ->sort(function (array $a, array $b): int {
                return $b['score'] <=> $a['score']
                    ?: ($a['chunk']->document_id <=> $b['chunk']->document_id)
                    ?: ($a['chunk']->ordinal <=> $b['chunk']->ordinal);
            })
            ->take($k)
            ->map(fn (array $row): KnowledgeChunk => $row['chunk'])
            ->values();
    }
}
