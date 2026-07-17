<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\CvDocument;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CvDocument>
 */
class CvDocumentFactory extends Factory
{
    protected $model = CvDocument::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory()->state(['user_type' => 'student']),
            'version' => 1,
            'title' => 'Student — Data Engineer',
            'source' => CvDocument::SOURCE_MANUAL,
            'content_source' => 'ai',
            'content' => [
                'name' => 'Student', 'email' => 's@example.com', 'phone' => null,
                'headline' => 'Data Engineer in training',
                'summary' => 'Completed 4 modules with graded lab work.',
                'skills' => ['sql', 'python'],
                'projects' => [['name' => 'ETL lab', 'bullets' => ['Built a pipeline passing 6 tests']]],
                'education' => [], 'certifications' => [],
            ],
            'status' => CvDocument::STATUS_DRAFT,
        ];
    }
}
