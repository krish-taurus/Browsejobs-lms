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
 * Seeds the seven BrowseJobs programs (Platform Spec v1.0 §6.1): 4 live +
 * 3 waitlist. Live courses get a sample module/topic/lesson tree so the
 * curriculum and batch features are demo-able; the REAL ~8–9-module syllabi
 * live in the brochures and replace these samples when supplied (spec §14.7).
 */
class CurriculumSeeder extends Seeder
{
    /**
     * @var array<string, array{name: string, status: string, tagline: string}>
     */
    private const COURSES = [
        'DE' => ['name' => 'Data Engineering', 'status' => 'live', 'tagline' => 'Pipelines, warehouses, and the modern data stack.'],
        'DC' => ['name' => 'DevOps & Cloud', 'status' => 'live', 'tagline' => 'Ship, scale, and run production systems.'],
        'PB' => ['name' => 'Python Backend', 'status' => 'live', 'tagline' => 'APIs, databases, and production Python.'],
        'DA' => ['name' => 'Data Analytics', 'status' => 'live', 'tagline' => 'SQL, dashboards, and decisions from data.'],
        'AA' => ['name' => 'Agentic AI', 'status' => 'coming_soon', 'tagline' => 'Build with LLMs, agents, and tools.'],
        'CS' => ['name' => 'Cyber Security', 'status' => 'coming_soon', 'tagline' => 'Defend real systems against real attacks.'],
        'SN' => ['name' => 'ServiceNow', 'status' => 'coming_soon', 'tagline' => 'The enterprise workflow platform.'],
    ];

    public function run(): void
    {
        $tenant = Tenant::query()->where('slug', 'browsejobs')->firstOrFail();

        $program = Program::query()->updateOrCreate(
            ['tenant_id' => $tenant->id, 'slug' => 'career-tracks'],
            ['name' => 'Career Tracks', 'description' => 'BrowseJobs job-ready programs, reverse-engineered from live interview demand.'],
        );

        $position = 0;

        foreach (self::COURSES as $code => $definition) {
            $course = Course::query()->updateOrCreate(
                ['tenant_id' => $tenant->id, 'code' => $code],
                [
                    'program_id' => $program->id,
                    'name' => $definition['name'],
                    'slug' => Str::slug($definition['name']),
                    'status' => $definition['status'],
                    'tagline' => $definition['tagline'],
                    'description' => "{$definition['name']} — built from real interviews.",
                    'position' => $position++,
                ],
            );

            if ($definition['status'] === 'live') {
                $this->seedModules($tenant->id, $course);
            }
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
