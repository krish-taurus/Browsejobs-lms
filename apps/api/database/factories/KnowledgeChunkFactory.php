<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\KnowledgeChunk;
use App\Models\KnowledgeDocument;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<KnowledgeChunk>
 */
class KnowledgeChunkFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $content = fake()->paragraph();

        return [
            'tenant_id' => Tenant::factory(),
            'document_id' => KnowledgeDocument::factory(),
            'ordinal' => 0,
            'content' => $content,
            'token_estimate' => (int) ceil(mb_strlen($content) / 4),
            'term_count' => count(array_unique(preg_split('/\W+/', strtolower($content)) ?: [])),
            'embedding' => null,
        ];
    }
}
