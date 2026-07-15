<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\LessonType;
use App\Models\Course;
use App\Models\Lesson;
use App\Models\Module;
use App\Models\Program;
use App\Models\Tenant;
use App\Models\Topic;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Seeds the seven BrowseJobs courses with a sample module/topic/lesson tree so
 * the curriculum and batch features are demo-able immediately.
 */
class CurriculumSeeder extends Seeder
{
    /**
     * @var array<string, string>
     */
    private const COURSES = [
        'DE' => 'Data Engineering',
        'FS' => 'Full-Stack Development',
        'DS' => 'Data Science',
        'DA' => 'Data Analytics',
        'CD' => 'Cloud & DevOps',
        'QA' => 'Software Testing (QA)',
        'UX' => 'UI/UX Design',
    ];

    public function run(): void
    {
        $tenant = Tenant::query()->where('slug', 'browsejobs')->firstOrFail();

        $program = Program::query()->updateOrCreate(
            ['tenant_id' => $tenant->id, 'slug' => 'career-tracks'],
            ['name' => 'Career Tracks', 'description' => 'BrowseJobs job-ready programs.'],
        );

        $position = 0;

        foreach (self::COURSES as $code => $name) {
            $course = Course::query()->updateOrCreate(
                ['tenant_id' => $tenant->id, 'code' => $code],
                [
                    'program_id' => $program->id,
                    'name' => $name,
                    'slug' => Str::slug($name),
                    'description' => "{$name} — from fundamentals to a placement-ready portfolio.",
                    'position' => $position++,
                ],
            );

            $this->seedModules($tenant->id, $course);
        }
    }

    private function seedModules(int $tenantId, Course $course): void
    {
        foreach (['Foundations', 'Core Skills', 'Capstone'] as $mIndex => $moduleName) {
            $module = Module::query()->updateOrCreate(
                ['tenant_id' => $tenantId, 'course_id' => $course->id, 'name' => $moduleName],
                ['position' => $mIndex],
            );

            foreach (['Concepts', 'Hands-on Lab'] as $tIndex => $topicName) {
                $topic = Topic::query()->updateOrCreate(
                    ['tenant_id' => $tenantId, 'module_id' => $module->id, 'name' => $topicName],
                    ['position' => $tIndex],
                );

                Lesson::query()->updateOrCreate(
                    ['tenant_id' => $tenantId, 'topic_id' => $topic->id, 'title' => "{$topicName}: Live Session"],
                    ['type' => LessonType::LiveClass->value, 'position' => 0],
                );
                Lesson::query()->updateOrCreate(
                    ['tenant_id' => $tenantId, 'topic_id' => $topic->id, 'title' => "{$topicName}: Assignment"],
                    ['type' => LessonType::Assignment->value, 'position' => 1],
                );
            }
        }
    }
}
