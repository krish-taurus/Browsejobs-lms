<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\KnowledgeDocument;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<KnowledgeDocument>
 */
class KnowledgeDocumentFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'title' => fake()->sentence(4),
            'source_type' => 'manual',
            'source_id' => null,
            'body' => fake()->paragraphs(3, true),
            'course_id' => null,
            'lesson_id' => null,
            'is_active' => true,
            'authored_by' => null,
        ];
    }
}
