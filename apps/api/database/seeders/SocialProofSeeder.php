<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Course;
use App\Models\CourseInterviewQuestion;
use App\Models\PlacementStory;
use App\Models\Review;
use App\Models\Tenant;
use Illuminate\Database\Seeder;

/**
 * Course-page social proof for the four live courses: per-course interview-
 * question banks, demo placement stories, and the real Google/JustDial reviews.
 * Idempotent — runs after CurriculumSeeder (needs the courses).
 *
 * Honesty rules (CLAUDE.md, binding): fabricated-experience and salary claims
 * must NEVER render publicly, so the demo placement cards are seeded as DRAFTS
 * (consent=false, is_published=false). They exist only in the admin panel as a
 * layout preview to be edited into — and replaced by — real, consented stories
 * before anyone publishes them. The interview questions are honest content
 * (real question shapes, not personal claims) so they publish live. The reviews
 * are genuine — real people, real words.
 */
class SocialProofSeeder extends Seeder
{
    public function run(): void
    {
        $tenant = Tenant::query()->where('slug', 'browsejobs')->firstOrFail();

        $courses = Course::query()
            ->where('tenant_id', $tenant->id)
            ->whereIn('slug', array_keys(self::QUESTION_BANKS))
            ->get()
            ->keyBy('slug');

        foreach (self::QUESTION_BANKS as $slug => $rounds) {
            $course = $courses->get($slug);
            if ($course === null) {
                continue;
            }

            $this->questions($tenant->id, $course->id, $rounds);
            $this->stories($tenant->id, $course->id, self::DEMO_STORIES[$slug] ?? []);
        }

        $this->reviews($tenant->id);
    }

    /**
     * Demo placement cards, seeded as DRAFTS (never public). A few per course so
     * the admin has a layout to edit into a real, consented story.
     *
     * @param  list<array<string, mixed>>  $stories
     */
    private function stories(int $tenantId, int $courseId, array $stories): void
    {
        foreach (array_values($stories) as $position => $s) {
            PlacementStory::query()->updateOrCreate(
                ['tenant_id' => $tenantId, 'course_id' => $courseId, 'student_name' => $s['student_name']],
                array_merge($s, ['position' => $position, 'consent' => false, 'is_published' => false]),
            );
        }
    }

    /**
     * Publish the course's interview-question bank (grouped by round).
     *
     * @param  list<array{0:int, 1:string, 2:list<string>}>  $rounds
     */
    private function questions(int $tenantId, int $courseId, array $rounds): void
    {
        foreach ($rounds as [$roundNo, $roundName, $questions]) {
            foreach (array_values($questions) as $i => $question) {
                CourseInterviewQuestion::query()->updateOrCreate(
                    ['tenant_id' => $tenantId, 'course_id' => $courseId, 'round_no' => $roundNo, 'question' => $question],
                    ['round_name' => $roundName, 'is_published' => true, 'position' => $i],
                );
            }
        }
    }

    /** Real reviews (genuine people, genuine words) tagged to the DE course. */
    private function reviews(int $tenantId): void
    {
        $reviews = [
            ['author_name' => 'Chakravarthi Kuraba', 'author_meta' => '2 reviews', 'source' => 'google', 'body' => 'I am incredibly grateful to BrowseJobs for helping me build a strong foundation in data engineering. The course was well-structured, covering Python, SQL, AWS, and PySpark with hands-on projects. Krish and his team were always supportive. Thanks to their training and mentorship, I was able to secure a job as a data engineer. I highly recommend BrowseJobs.'],
            ['author_name' => 'supritha selvapandiyan', 'author_meta' => 'Local Guide · 17 reviews', 'source' => 'google', 'body' => 'I would like to thank Krish Sir from the bottom of my heart for his exceptional support in training us with industry relevant experience. His dedication and expertise have been instrumental in my career growth, providing me with the skills and confidence needed to succeed in the job market. I highly recommend BrowseJobs. Thanks a lot Sir!!'],
            ['author_name' => 'Niloy Roy', 'author_meta' => '1 review', 'source' => 'google', 'body' => "I was quite tensed after my graduation thinking on which path I should go — at that critical moment I came to know Browsejobs. I chose 'Data Engineer' which is impressive. Thanks to all Browsejobs members, specially Krish sir — his way of teaching is literally fabulous. I strongly recommend if you are in your career building phase. Thank u"],
            ['author_name' => 'Neha kumari', 'author_meta' => '1 review · 1 photo', 'source' => 'google', 'body' => 'It was a great experience being here, and I am thrilled to have been a part of this course. This platform has truly been the best place to learn and grow. I sincerely thank Krish Sir for his unwavering support and guidance throughout this journey.'],
            ['author_name' => 'Anjali Yokshi', 'author_meta' => 'Local Guide · 1 review', 'source' => 'google', 'body' => "One of the best IT trainings for everyone who wants to fulfill their dreams. The way Krish sir motivated and supported the students is really good — he gave everyone confidence to get a job. I have never seen such a mentor, and the BrowseJobs team supported us very well. Don't think too much — this is the best platform to join."],
            ['author_name' => 'Venkateswarlu', 'author_meta' => '2 reviews · Expert faculty', 'source' => 'justdial', 'body' => "Browse jobs is one of the best training institutes in Bangalore. Krish sir's training is excellent, practical and easy to understand. Special thanks to Priyanka, Somali, Dhanya, Swathi and Anjali madam for their continuous support. The team not only provides quality training but also helps with interview preparation and job opportunities. Highly recommend."],
        ];

        foreach ($reviews as $order => $r) {
            Review::query()->updateOrCreate(
                ['tenant_id' => $tenantId, 'author_name' => $r['author_name']],
                [
                    'author_meta' => $r['author_meta'],
                    'course_slug' => 'data-engineering',
                    'body' => $r['body'],
                    'rating' => 5,
                    'source' => $r['source'],
                    'is_active' => true,
                    'sort_order' => $order,
                ],
            );
        }
    }

    /**
     * Curated interview-question banks — a few per round, domain-appropriate,
     * reverse-engineered (with variations) from real interviews. Not the full
     * dump; enough to be useful and demo-able. Keyed by course slug.
     *
     * @var array<string, list<array{0:int, 1:string, 2:list<string>}>>
     */
    private const QUESTION_BANKS = [
        'data-engineering' => [
            [1, 'Screening — recruiter + hiring manager', [
                'Walk me through a data pipeline you built end to end.',
                'SQL: find the 2nd highest salary per department.',
                'Star schema vs snowflake — when would you pick each?',
            ]],
            [2, 'SQL & Python round', [
                'Deduplicate rows keeping the latest by timestamp (window function).',
                'Difference between RANK, DENSE_RANK and ROW_NUMBER.',
                'Python: flatten a deeply nested JSON into a flat table.',
                'What makes a Spark job spill to disk, and how do you fix it?',
            ]],
            [3, 'Data modelling & systems', [
                'Design an ingestion pipeline for 50M events/day — batch or streaming?',
                'How do you handle late-arriving / out-of-order data?',
                'Partitioning vs bucketing in Spark — the trade-offs?',
                'How would you guarantee exactly-once processing?',
            ]],
            [4, 'System design & behavioural', [
                'Design a data warehouse for an e-commerce company.',
                'How do you ensure data quality across pipelines?',
                'Tell me about a time a pipeline broke in production — what did you do?',
            ]],
            [5, 'Scenario & project-based', [
                'Your daily S3 ingestion suddenly loads duplicate records. How do you detect and de-duplicate them?',
                'A Spark job keeps failing with executor OOM errors. What do you check and change?',
                'The source added a new column overnight and broke your Glue/Spark ETL. How do you handle schema evolution?',
                'One key holds 90% of the records and your PySpark job is skewed. How do you fix the skew?',
                'A batch pipeline must finish before 8 AM but data volume just doubled. How do you keep the SLA?',
            ]],
        ],

        'devops-cloud' => [
            [1, 'Screening — recruiter + hiring manager', [
                'Walk me through everything that happens from `git push` to production in a pipeline you built.',
                'Explain the difference between a container and a virtual machine.',
                'Deployment vs StatefulSet vs DaemonSet in Kubernetes — when each?',
            ]],
            [2, 'Docker & Kubernetes', [
                'How do you shrink a Docker image? Walk through a multi-stage build.',
                'A pod is stuck in CrashLoopBackOff — how do you debug it step by step?',
                'How does a Kubernetes Service route traffic to the right pods?',
                'Liveness vs readiness probes — what breaks if you confuse them?',
            ]],
            [3, 'CI/CD & Infrastructure as Code', [
                'How do you store and inject secrets in a CI/CD pipeline?',
                'Terraform: difference between `plan` and `apply`, and how do you handle remote state locking?',
                'Design a zero-downtime release — blue-green or canary, and why?',
            ]],
            [4, 'Cloud architecture & reliability', [
                'Design a highly available web app on AWS across multiple availability zones.',
                'Latency spikes at 9 AM every day — how do you investigate it?',
                'How do you set up autoscaling, and which metrics should drive it?',
            ]],
            [5, 'Scenario & behavioural', [
                'A deploy just pushed error rates up 40%. Walk me through your rollback and incident response.',
                'Terraform state has drifted from the real infrastructure. How do you reconcile it safely?',
                'The cluster is out of resources and pods are being evicted. What do you do first?',
                'Tell me about a production outage you owned and what you changed so it could not recur.',
            ]],
        ],

        'python-backend' => [
            [1, 'Screening — recruiter + hiring manager', [
                'Walk me through a REST API you designed end to end.',
                'List vs tuple vs set — when do you reach for each?',
                'What is the GIL and how does it affect concurrency in Python?',
            ]],
            [2, 'Python & data structures', [
                'How does reference counting plus the cyclic garbage collector work in CPython?',
                'Difference between @staticmethod, @classmethod and an instance method.',
                'Generators vs lists — when is a generator the right call?',
                'Reverse a linked list, or check if a string has all unique characters — code one.',
            ]],
            [3, 'APIs & databases', [
                'Design the database schema for a URL shortener.',
                'How do you prevent SQL injection and the N+1 query problem with an ORM?',
                'Add pagination, filtering and rate limiting to an existing endpoint — how?',
                'WSGI vs ASGI — when do you need async views?',
            ]],
            [4, 'System design & behavioural', [
                'Design a rate limiter for a public API.',
                'How would you scale a Python service to handle 10x traffic?',
                'Tell me about a production bug you tracked down and how you found the root cause.',
            ]],
            [5, 'Scenario & project-based', [
                'An endpoint is slow under load. How do you profile it and where do you look first?',
                'A background Celery task keeps failing silently. How do you make it observable and reliable?',
                'You need caching to cut database load — what do you cache and how do you invalidate it?',
                'Two requests update the same row and the data ends up inconsistent. How do you handle the race?',
            ]],
        ],

        'data-analytics' => [
            [1, 'Screening — recruiter + hiring manager', [
                'Tell me about an analysis where your insight changed a business decision.',
                'SQL: top 3 products by revenue within each category.',
                'What is the difference between a measure and a dimension?',
            ]],
            [2, 'SQL & spreadsheets', [
                'WHERE vs HAVING — when do you use each?',
                'Write a query for month-over-month growth.',
                'INNER vs LEFT JOIN — give an example where the result differs.',
                'How do you handle NULLs when you aggregate?',
            ]],
            [3, 'Dashboards & visualisation (Power BI / Tableau)', [
                'Line vs bar vs scatter — how do you choose for a given question?',
                'Explain a star schema and why it matters for a BI model.',
                'Calculated column vs measure in Power BI / DAX — what is the difference?',
                'A dashboard is slow on a large dataset. How do you speed it up?',
            ]],
            [4, 'Statistics & business sense', [
                'Explain p-value and significance to a non-technical stakeholder.',
                'How would you measure whether a marketing campaign actually worked?',
                'Correlation vs causation — give an example of confusing the two.',
            ]],
            [5, 'Scenario & case', [
                'Sales dropped 15% last quarter. How do you find out why?',
                'A stakeholder asks for a metric that would mislead the reader. How do you handle it?',
                'Two data sources disagree on the same number. What do you do?',
                'Design the KPIs for an e-commerce executive dashboard.',
            ]],
        ],
    ];

    /**
     * Demo placement cards — DRAFTS ONLY (never public). Names avoid a uniform
     * pattern; quotes read like real texts. Keyed by course slug.
     *
     * @var array<string, list<array<string, mixed>>>
     */
    private const DEMO_STORIES = [
        'data-engineering' => [
            ['student_name' => 'Arjun Mehta', 'before_label' => 'Mechanical engineer, 2 yrs no job', 'after_role' => 'Data Engineer', 'package_label' => '₹11.5 LPA', 'company_name' => 'Nimbus Data', 'company_color' => '#12408f', 'rounds' => 4, 'quote' => 'sir i got the offer!! 😭🔥 4 rounds and honestly the SQL round was exactly the stuff you drilled us on 🙏'],
            ['student_name' => 'Fatima Noor', 'before_label' => 'Bank teller, self-taught at night', 'after_role' => 'Analytics Engineer', 'package_label' => '₹9.0 LPA', 'company_name' => 'Cobalt Systems', 'company_color' => '#5b3fb0', 'rounds' => 3, 'quote' => '3 rounds done and cleared 🥹 not gonna lie your mock interviews felt harder than the actual one'],
        ],
        'devops-cloud' => [
            ['student_name' => 'Sandeep Rao', 'before_label' => 'IT support, stuck 3 yrs', 'after_role' => 'DevOps Engineer', 'package_label' => '₹10.5 LPA', 'company_name' => 'Vertex Cloud', 'company_color' => '#0f6d5b', 'rounds' => 4, 'quote' => 'krish they literally asked me to debug a crashloopbackoff live 😅 did it in 2 mins because of your labs. offer letter in hand!'],
            ['student_name' => 'Meghna Iyer', 'before_label' => 'Manual QA tester', 'after_role' => 'Cloud / SRE', 'package_label' => '₹12.0 LPA', 'company_name' => 'Halcyon Labs', 'company_color' => '#1b4d7a', 'rounds' => 3, 'quote' => 'thank you so much to the whole browsejobs team 🙏 the terraform round felt like a class recording lol'],
        ],
        'python-backend' => [
            ['student_name' => 'Imran Qureshi', 'before_label' => 'Frontend dev wanting backend', 'after_role' => 'Backend Engineer', 'package_label' => '₹10.0 LPA', 'company_name' => 'Larkspur Tech', 'company_color' => '#2b3a8f', 'rounds' => 3, 'quote' => 'got it 🎉 they asked me to design a rate limiter and i had already built one in the mock. felt so prepared'],
            ['student_name' => 'Sneha Pillai', 'before_label' => 'BSc grad, no coding job', 'after_role' => 'Python Developer', 'package_label' => '₹8.5 LPA', 'company_name' => 'Brightwave', 'company_color' => '#7a3f6d', 'rounds' => 4, 'quote' => 'from zero to a dev job 🥹 thanks krish sir and the whole team, i still cant believe it'],
        ],
        'data-analytics' => [
            ['student_name' => 'Karthik Reddy', 'before_label' => 'Accountant, wanted out', 'after_role' => 'Data Analyst', 'package_label' => '₹8.0 LPA', 'company_name' => 'Meridian Retail', 'company_color' => '#0e5a8a', 'rounds' => 3, 'quote' => 'the sql round was exactly the type of questions we practiced 🙌 cleared it and got the offer today!'],
            ['student_name' => 'Divya Menon', 'before_label' => 'School teacher for 6 yrs', 'after_role' => 'BI Analyst', 'package_label' => '₹9.5 LPA', 'company_name' => 'Northwind Systems', 'company_color' => '#5b3fb0', 'rounds' => 3, 'quote' => 'career change at 31 and it worked 😭 thank you browsejobs, the power bi mock saved me in the last round'],
        ],
    ];
}
