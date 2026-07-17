<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Actions\Content\ApproveSyllabus;
use App\Models\Course;
use App\Models\Syllabus;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Syllabus\SyllabusHasher;
use App\Support\Tenancy\TenantContext;
use Illuminate\Database\Seeder;
use Throwable;

/**
 * Makes the AI Syllabus Generator (P3.5c) demo-able: attaches an approved, rendered
 * syllabus to a seeded course so the public course-page lead-gated download, the
 * student dashboard download, and the admin builder all have data. Content is authored
 * inline (the AI path is exercised by tests) so seeding needs no AI key.
 */
class SyllabusSeeder extends Seeder
{
    public function run(): void
    {
        $tenant = Tenant::query()->where('slug', 'browsejobs')->first();
        if ($tenant === null) {
            return;
        }

        app(TenantContext::class)->run($tenant, function () use ($tenant): void {
            $course = Course::query()->orderBy('position')->orderBy('id')->first();
            if ($course === null || Syllabus::query()->where('course_id', $course->id)->exists()) {
                return;
            }

            $staff = User::query()->where('user_type', 'staff')->first();

            $syllabus = Syllabus::query()->create([
                'tenant_id' => $tenant->id,
                'course_id' => $course->id,
                'title' => $course->name.' — Syllabus',
                'content' => "## Overview\n\n{$course->name} is built from real interviews — the syllabus is "
                    ."reverse-engineered from what hiring teams actually ask.\n\n"
                    ."## Learning outcomes\n\n- Work confidently across the core stack.\n"
                    ."- Build and ship a portfolio-grade capstone.\n- Explain your decisions in an interview.\n\n"
                    ."## Weekly plan\n\n- Each module maps to a focused week of live classes, labs, and a quiz.\n\n"
                    ."## Who this is for\n\n- Career switchers and early-career engineers ready to put in the reps.",
                'curriculum_hash' => app(SyllabusHasher::class)->hash($course),
            ]);

            // Approve → renders the branded document + publishes it. Guarded so a
            // scratch seed without object storage still leaves a usable draft.
            try {
                app(ApproveSyllabus::class)->handle($syllabus, $staff);
            } catch (Throwable) {
                // Render disk unavailable in this environment — the draft still demos the flow.
            }
        });
    }
}
