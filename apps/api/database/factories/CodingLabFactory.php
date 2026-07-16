<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\CodeLanguage;
use App\Models\CodingLab;
use App\Models\Lesson;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CodingLab>
 */
class CodingLabFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'lesson_id' => Lesson::factory()->codingLab(),
            'language' => CodeLanguage::Python->value,
            'instructions' => 'Read two integers and print their sum.',
            'starter_code' => "a = int(input())\nb = int(input())\n# print the sum\n",
            'test_cases' => [
                ['stdin' => "2\n3\n", 'expected_stdout' => '5'],
                ['stdin' => "10\n20\n", 'expected_stdout' => '30'],
            ],
            'time_limit_ms' => 5000,
        ];
    }
}
