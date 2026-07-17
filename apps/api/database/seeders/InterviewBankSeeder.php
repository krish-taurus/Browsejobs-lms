<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\MockBlueprint;
use App\Models\RealInterviewQuestion;
use App\Models\Tenant;
use App\Support\Tenancy\TenantContext;
use Illuminate\Database\Seeder;

/**
 * Demo bank content (P4.2) so the review queue, analytics, mock question
 * sourcing and gap report are demo-able out of the box. Generic technical
 * questions only — no company names or fabricated "asked at X" claims; the
 * real bank fills from the transcript pipeline. Idempotent.
 */
class InterviewBankSeeder extends Seeder
{
    /** @var array<string, list<array{q: string, topics: list<string>, difficulty: string, asked: int, status: string}>> */
    private const BY_ROLE = [
        'Data Engineer' => [
            ['q' => 'A daily batch job that took 20 minutes now takes 4 hours. Walk me through how you would diagnose it.', 'topics' => ['pipelines & orchestration', 'debugging'], 'difficulty' => 'medium', 'asked' => 9, 'status' => 'approved'],
            ['q' => 'How would you optimise a query joining two large tables where the join column is skewed?', 'topics' => ['sql optimisation'], 'difficulty' => 'hard', 'asked' => 12, 'status' => 'approved'],
            ['q' => 'Design a pipeline that ingests CSV drops from an SFTP server into a warehouse with dedupe and late-arrival handling.', 'topics' => ['pipelines & orchestration', 'data modelling'], 'difficulty' => 'medium', 'asked' => 7, 'status' => 'approved'],
            ['q' => 'What is the difference between a star schema and a snowflake schema, and when would you denormalise?', 'topics' => ['data modelling', 'sql optimisation'], 'difficulty' => 'easy', 'asked' => 10, 'status' => 'approved'],
            ['q' => 'In Python, how would you process a 10 GB file on a machine with 2 GB of RAM?', 'topics' => ['python'], 'difficulty' => 'medium', 'asked' => 8, 'status' => 'approved'],
            ['q' => 'Explain idempotency in data pipelines and how you would make a re-run safe.', 'topics' => ['pipelines & orchestration'], 'difficulty' => 'medium', 'asked' => 5, 'status' => 'pending'],
        ],
        'QA / Automation Test Engineer' => [
            ['q' => 'You have zero automation and a release every two weeks. What do you automate first and why?', 'topics' => ['automation frameworks', 'test strategy'], 'difficulty' => 'medium', 'asked' => 8, 'status' => 'approved'],
            ['q' => 'How do you handle flaky tests in a CI pipeline?', 'topics' => ['ci/cd', 'automation frameworks'], 'difficulty' => 'medium', 'asked' => 11, 'status' => 'approved'],
            ['q' => 'Write the test cases you would run for an OTP login form.', 'topics' => ['testing fundamentals'], 'difficulty' => 'easy', 'asked' => 9, 'status' => 'approved'],
            ['q' => 'How would you test a REST API without any UI — what tools and what checks?', 'topics' => ['api testing'], 'difficulty' => 'medium', 'asked' => 7, 'status' => 'approved'],
        ],
        'Java Full Stack Developer' => [
            ['q' => 'Explain how Spring dependency injection works and why constructor injection is preferred.', 'topics' => ['spring & rest apis'], 'difficulty' => 'medium', 'asked' => 10, 'status' => 'approved'],
            ['q' => 'What happens when two threads call the same synchronized method on one object?', 'topics' => ['core java & oop', 'concurrency'], 'difficulty' => 'hard', 'asked' => 6, 'status' => 'approved'],
            ['q' => 'Design the REST endpoints for a shopping cart, including status codes and idempotency.', 'topics' => ['spring & rest apis', 'system design'], 'difficulty' => 'medium', 'asked' => 8, 'status' => 'approved'],
            ['q' => 'When would you choose an ArrayList over a LinkedList, with a concrete example?', 'topics' => ['core java & oop'], 'difficulty' => 'easy', 'asked' => 12, 'status' => 'approved'],
        ],
    ];

    public function run(): void
    {
        $tenant = Tenant::query()->where('slug', 'browsejobs')->first();
        if ($tenant === null) {
            return;
        }

        app(TenantContext::class)->run($tenant, function () use ($tenant): void {
            foreach (self::BY_ROLE as $role => $questions) {
                $courseId = MockBlueprint::query()->where('role_title', $role)->value('course_id');

                foreach ($questions as $q) {
                    RealInterviewQuestion::query()->updateOrCreate(
                        [
                            'tenant_id' => $tenant->id,
                            'fingerprint' => RealInterviewQuestion::fingerprintFor($role, $q['q']),
                        ],
                        [
                            'course_id' => $courseId,
                            'role_title' => $role,
                            'question' => $q['q'],
                            'normalized_question' => mb_strtolower($q['q']),
                            'topic_tags' => $q['topics'],
                            'difficulty' => $q['difficulty'],
                            'round_type' => 'technical',
                            'confidence' => 'high',
                            'status' => $q['status'],
                            'asked_count' => $q['asked'],
                            'last_seen_at' => now()->subDays($q['asked']),
                        ],
                    );
                }
            }
        });
    }
}
