<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\CodeLanguage;
use App\Enums\LessonType;
use App\Models\CodingLab;
use App\Models\Course;
use App\Models\Lesson;
use App\Models\Module;
use App\Models\Tenant;
use App\Models\Topic;
use App\Support\Tenancy\TenantContext;
use Illuminate\Database\Seeder;

/**
 * Seeds one demo coding lab (Python "sum two numbers") so the labs page is
 * demo-able (PRD §6.5). Idempotent.
 */
class LabSeeder extends Seeder
{
    public function run(): void
    {
        $tenant = Tenant::query()->where('slug', 'browsejobs')->first();
        if ($tenant === null) {
            return;
        }

        app(TenantContext::class)->run($tenant, function () use ($tenant): void {
            $course = Course::query()->firstOrCreate(
                ['tenant_id' => $tenant->id, 'code' => 'PRAC'],
                ['name' => 'Practice', 'slug' => 'practice', 'status' => 'live'],
            );
            $module = Module::query()->firstOrCreate(
                ['tenant_id' => $tenant->id, 'course_id' => $course->id, 'name' => 'Warm-ups'],
                ['position' => 1],
            );
            $topic = Topic::query()->firstOrCreate(
                ['tenant_id' => $tenant->id, 'module_id' => $module->id, 'name' => 'Basics'],
                ['position' => 1],
            );
            $lesson = Lesson::query()->firstOrCreate(
                ['tenant_id' => $tenant->id, 'topic_id' => $topic->id, 'title' => 'Sum two numbers'],
                ['type' => LessonType::CodingLab->value, 'position' => 1],
            );

            CodingLab::query()->updateOrCreate(
                ['tenant_id' => $tenant->id, 'lesson_id' => $lesson->id],
                [
                    'language' => CodeLanguage::Python->value,
                    'instructions' => 'Read two integers, each on its own line, and print their sum.',
                    'starter_code' => "a = int(input())\nb = int(input())\n# print the sum below\n",
                    'test_cases' => [
                        ['stdin' => "2\n3\n", 'expected_stdout' => '5'],
                        ['stdin' => "10\n20\n", 'expected_stdout' => '30'],
                        ['stdin' => "-4\n9\n", 'expected_stdout' => '5'],
                    ],
                    'time_limit_ms' => 5000,
                ],
            );
        });
    }
}
