<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\RealInterviewQuestion;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RealInterviewQuestion>
 */
class RealInterviewQuestionFactory extends Factory
{
    protected $model = RealInterviewQuestion::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $question = 'How would you optimise a slow SQL join over '.fake()->unique()->numberBetween(1, 100000).' rows?';

        return [
            'role_title' => 'Data Engineer',
            'question' => $question,
            'normalized_question' => mb_strtolower($question),
            'fingerprint' => RealInterviewQuestion::fingerprintFor('Data Engineer', $question),
            'topic_tags' => ['sql optimisation'],
            'difficulty' => 'medium',
            'round_type' => 'technical',
            'confidence' => 'high',
            'status' => RealInterviewQuestion::STATUS_APPROVED,
            'asked_count' => 1,
            'last_seen_at' => now(),
        ];
    }

    public function pending(): static
    {
        return $this->state(['status' => RealInterviewQuestion::STATUS_PENDING]);
    }
}
