<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\NoteSource;
use App\Enums\NoteStatus;
use App\Models\Lesson;
use App\Models\LessonNote;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LessonNote>
 */
class LessonNoteFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'lesson_id' => Lesson::factory()->state(['type' => 'notes']),
            'transcript' => fake()->paragraphs(4, true),
            'notes' => null,
            'source' => NoteSource::Paste->value,
            'status' => NoteStatus::Draft->value,
        ];
    }

    public function withNotes(): static
    {
        return $this->state(fn (array $attributes): array => [
            'notes' => "## Key concepts\n\n- Recursion needs a base case.\n\n## Takeaways\n\n- Practice tracing calls.",
        ]);
    }

    public function approved(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => NoteStatus::Approved->value,
            'approved_at' => now(),
            'notes' => $attributes['notes'] ?? '## Notes\n\nApproved study notes.',
        ]);
    }
}
