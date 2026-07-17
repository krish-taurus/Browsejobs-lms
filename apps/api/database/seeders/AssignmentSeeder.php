<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\AssignmentSubmissionStatus;
use App\Enums\GradeStatus;
use App\Enums\LessonType;
use App\Models\Assignment;
use App\Models\AssignmentGrade;
use App\Models\AssignmentSubmission;
use App\Models\BatchMember;
use App\Models\Lesson;
use App\Models\Tenant;
use App\Models\Topic;
use App\Support\Tenancy\TenantContext;
use Illuminate\Database\Seeder;

/**
 * Makes AI assignment grading (P3.4b) demo-able: an assignment lesson with a rubric,
 * a student submission, and one released grade so the "My grades" view + grading queue
 * both have content.
 */
class AssignmentSeeder extends Seeder
{
    public function run(): void
    {
        $tenant = Tenant::query()->where('slug', 'browsejobs')->first();
        if ($tenant === null) {
            return;
        }

        app(TenantContext::class)->run($tenant, function () use ($tenant): void {
            $lesson = Lesson::query()->where('type', LessonType::Assignment->value)->orderBy('id')->first();
            if ($lesson === null) {
                $topic = Topic::query()->orderBy('id')->first();
                if ($topic === null) {
                    return;
                }
                $lesson = Lesson::query()->create([
                    'tenant_id' => $tenant->id, 'topic_id' => $topic->id,
                    'title' => 'Build a REST endpoint', 'type' => LessonType::Assignment->value, 'position' => 98,
                ]);
            }

            $rubric = [
                ['criterion' => 'Correctness', 'max_points' => 50],
                ['criterion' => 'Code quality', 'max_points' => 30],
                ['criterion' => 'Documentation', 'max_points' => 20],
            ];

            $assignment = Assignment::query()->firstOrCreate(
                ['lesson_id' => $lesson->id],
                [
                    'tenant_id' => $tenant->id, 'title' => 'Build a REST endpoint',
                    'instructions' => 'Implement the endpoint described in class and submit your repo link.',
                    'rubric' => $rubric, 'max_points' => array_sum(array_column($rubric, 'max_points')),
                ],
            );

            $member = BatchMember::query()->whereIn('status', ['enrolled', 'reserved', 'payment_pending', 'completed'])->first();
            if ($member === null) {
                return;
            }

            $submission = AssignmentSubmission::query()->firstOrCreate(
                ['assignment_id' => $assignment->id, 'user_id' => $member->user_id],
                [
                    'tenant_id' => $tenant->id, 'body' => 'Here is my implementation.',
                    'link' => 'https://github.com/example/rest-endpoint',
                    'status' => AssignmentSubmissionStatus::Graded->value, 'submitted_at' => now()->subDay(),
                ],
            );

            AssignmentGrade::query()->firstOrCreate(
                ['submission_id' => $submission->id],
                [
                    'tenant_id' => $tenant->id, 'status' => GradeStatus::Approved->value,
                    'score' => 82, 'max_points' => 100,
                    'feedback' => 'Strong solution — tidy up the README and add a couple of tests.',
                    'criteria' => [
                        ['criterion' => 'Correctness', 'score' => 45, 'note' => 'Handles the core cases.'],
                        ['criterion' => 'Code quality', 'score' => 27, 'note' => 'Clean and readable.'],
                        ['criterion' => 'Documentation', 'score' => 10, 'note' => 'README is thin.'],
                    ],
                    'ai_likelihood' => 15, 'approved_at' => now(),
                ],
            );
        });
    }
}
