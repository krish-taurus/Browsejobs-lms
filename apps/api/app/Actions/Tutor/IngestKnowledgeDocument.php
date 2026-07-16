<?php

declare(strict_types=1);

namespace App\Actions\Tutor;

use App\Models\KnowledgeChunk;
use App\Models\KnowledgeDocument;
use App\Support\Tenancy\TenantContext;

/**
 * Chunks a knowledge document into `knowledge_chunks` for lexical retrieval (PRD
 * §6.4). Idempotent: replaces the document's existing chunks. Splits the body into
 * ~200-token windows on paragraph/sentence boundaries so citations stay coherent.
 */
final readonly class IngestKnowledgeDocument
{
    private const TARGET_TOKENS = 200;

    /** @return int number of chunks written */
    public function handle(KnowledgeDocument $document): int
    {
        return app(TenantContext::class)->run($document->tenant, function () use ($document): int {
            $document->chunks()->delete();

            $ordinal = 0;
            foreach ($this->split($document->body) as $content) {
                KnowledgeChunk::query()->create([
                    'tenant_id' => $document->tenant_id,
                    'document_id' => $document->id,
                    'ordinal' => $ordinal++,
                    'content' => $content,
                    'token_estimate' => $this->estimateTokens($content),
                    'term_count' => $this->termCount($content),
                ]);
            }

            return $ordinal;
        });
    }

    /**
     * Group paragraphs into windows of roughly TARGET_TOKENS, never splitting a
     * paragraph; an oversized paragraph becomes its own chunk.
     *
     * @return list<string>
     */
    private function split(string $body): array
    {
        $paragraphs = preg_split('/\n\s*\n/', trim($body), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        $chunks = [];
        $buffer = '';
        foreach ($paragraphs as $paragraph) {
            $paragraph = trim($paragraph);
            if ($paragraph === '') {
                continue;
            }

            $candidate = $buffer === '' ? $paragraph : $buffer."\n\n".$paragraph;
            if ($this->estimateTokens($candidate) > self::TARGET_TOKENS && $buffer !== '') {
                $chunks[] = $buffer;
                $buffer = $paragraph;
            } else {
                $buffer = $candidate;
            }
        }

        if ($buffer !== '') {
            $chunks[] = $buffer;
        }

        return $chunks === [] ? [trim($body)] : $chunks;
    }

    private function estimateTokens(string $text): int
    {
        return (int) ceil(mb_strlen($text) / 4);
    }

    private function termCount(string $text): int
    {
        $words = preg_split('/[^\p{L}\p{N}]+/u', mb_strtolower($text), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        return count(array_unique($words));
    }
}
