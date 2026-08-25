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
 * Seeds the seven BrowseJobs programs: 5 live + 2 waitlist. Live courses get a
 * sample module/topic/lesson tree so the curriculum and batch features are
 * demo-able; the REAL syllabi live in apps/web/src/content/courses.ts and
 * replace these samples when supplied (spec §14.7).
 *
 * AI Engineering (AE) supersedes the old "Agentic AI" (AA) waitlist entry, which
 * {@see self::retireSupersededCourses()} removes where it is safe to.
 */
class CurriculumSeeder extends Seeder
{
    /**
     * @var array<string, array{name: string, status: string, tagline: string}>
     */
    private const COURSES = [
        'DE' => ['name' => 'Data Engineering', 'status' => 'live', 'tagline' => 'Pipelines, warehouses, and the modern data stack.'],
        'DC' => ['name' => 'DevOps & Cloud', 'status' => 'live', 'tagline' => 'Ship, scale, and run production systems.'],
        'AE' => ['name' => 'AI Engineering', 'status' => 'live', 'tagline' => 'Agents, RAG and MCP — shipped to production.'],
        'PB' => ['name' => 'Python Backend', 'status' => 'live', 'tagline' => 'APIs, databases, and production Python.'],
        'DA' => ['name' => 'Data Analytics', 'status' => 'live', 'tagline' => 'SQL, dashboards, and decisions from data.'],
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

        $this->retireSupersededCourses($tenant->id);
    }

    /**
     * Remove course rows this seeder no longer defines, so a renamed track does
     * not leave a second, unreachable course behind — the old 'agentic-ai' slug
     * had no CourseDetail on the site, so its course page 404'd.
     *
     * Deliberately conservative: a course carrying modules or batches is left
     * alone and reported, because deleting it would take real student data with
     * it. Nothing here runs unless the row is genuinely empty.
     */
    private function retireSupersededCourses(int $tenantId): void
    {
        $superseded = Course::query()
            ->where('tenant_id', $tenantId)
            ->whereNotIn('code', array_keys(self::COURSES))
            ->withCount(['modules', 'batches'])
            ->get();

        foreach ($superseded as $course) {
            if ($course->modules_count > 0 || $course->batches_count > 0) {
                $this->command?->warn(
                    "Course {$course->code} ({$course->slug}) is superseded but still has "
                    ."{$course->modules_count} module(s) and {$course->batches_count} batch(es) — left in place."
                );

                continue;
            }

            $course->delete();
            $this->command?->info("Removed superseded course {$course->code} ({$course->slug}).");
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
                    [
                        'position' => $tIndex,
                        // Day-builder demo data (ADR 0049): every seeded topic is a
                        // numbered teaching day with keywords driving AI generation.
                        'day_number' => $tIndex + 1,
                        'keywords' => $tIndex === 0 ? ['fundamentals', 'core concepts'] : ['practice', 'hands-on'],
                        'summary' => $tIndex === 0
                            ? 'The core ideas of this module, from zero.'
                            : 'Apply what you learned in a guided lab.',
                    ],
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
