<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\InterviewTranscript;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InterviewTranscript>
 */
class InterviewTranscriptFactory extends Factory
{
    protected $model = InterviewTranscript::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'uploader_id' => User::factory(),
            'source' => 'text',
            'raw_text' => 'INTERVIEWER: Walk me through your last project. CANDIDATE: I built a pipeline.',
            'role_title' => 'Data Engineer',
            'status' => InterviewTranscript::STATUS_PARSED,
            'consent_confirmed_at' => now(),
        ];
    }
}
