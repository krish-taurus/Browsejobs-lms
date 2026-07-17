<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Course;
use App\Models\MockBlueprint;
use App\Models\Tenant;
use App\Support\Tenancy\TenantContext;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * One default interview blueprint per live course (PRD §6.6) so text mocks
 * work out of the box. Admins refine role/competencies at /admin/mocks;
 * P4.2 layers the real-interview question bank on top. Idempotent.
 */
class MockBlueprintSeeder extends Seeder
{
    /** @var array<string, array{role: string, competencies: list<string>, opening: string}> */
    private const BY_COURSE_KEYWORD = [
        'data' => [
            'role' => 'Data Engineer',
            'competencies' => ['SQL & data modelling', 'Python for data', 'Pipelines & orchestration', 'Cloud & warehousing', 'Communication'],
            'opening' => 'Welcome! Let\'s treat this like a real Data Engineer interview. To start: walk me through a data pipeline you have built or studied — what was the source, the transformations, and where did the data land?',
        ],
        'test' => [
            'role' => 'QA / Automation Test Engineer',
            'competencies' => ['Testing fundamentals', 'Automation frameworks', 'API testing', 'CI/CD awareness', 'Communication'],
            'opening' => 'Welcome! Let\'s treat this like a real QA Engineer interview. To start: how would you decide what to automate first in a web application that currently has zero test coverage?',
        ],
        'java' => [
            'role' => 'Java Full Stack Developer',
            'competencies' => ['Core Java & OOP', 'Spring & REST APIs', 'SQL & persistence', 'Frontend basics', 'Communication'],
            'opening' => 'Welcome! Let\'s treat this like a real Java Developer interview. To start: explain the difference between an interface and an abstract class, and give one situation where you would choose each.',
        ],
    ];

    private const DEFAULT = [
        'role' => 'Software Engineer',
        'competencies' => ['Fundamentals', 'Problem solving', 'Projects & practice', 'Communication'],
        'opening' => 'Welcome! Let\'s treat this like a real interview for your target role. To start: tell me about a project or exercise from your course you are most proud of, and your specific contribution.',
    ];

    public function run(): void
    {
        $tenant = Tenant::query()->where('slug', 'browsejobs')->first();
        if ($tenant === null) {
            return;
        }

        app(TenantContext::class)->run($tenant, function () use ($tenant): void {
            Course::query()->each(function (Course $course) use ($tenant): void {
                $spec = self::DEFAULT;
                foreach (self::BY_COURSE_KEYWORD as $keyword => $candidate) {
                    if (Str::contains(Str::lower($course->name), $keyword)) {
                        $spec = $candidate;
                        break;
                    }
                }

                MockBlueprint::query()->updateOrCreate(
                    ['tenant_id' => $tenant->id, 'course_id' => $course->id],
                    [
                        'role_title' => $spec['role'],
                        'competencies' => $spec['competencies'],
                        'opening_question' => $spec['opening'],
                        'is_active' => true,
                    ],
                );
            });
        });
    }
}
