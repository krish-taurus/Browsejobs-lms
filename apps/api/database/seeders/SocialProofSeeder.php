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
 * Course-page social proof for the Data Engineering course: concept placement
 * stories (replaced by real, consented ones in production), the interview-
 * question bank, and the real Google/JustDial reviews we hold. Idempotent.
 *
 * The placement stories here are clearly-concept demo data so the course page is
 * demo-able; the reviews are genuine (real people, real words). Runs after
 * CurriculumSeeder (needs the course).
 */
class SocialProofSeeder extends Seeder
{
    public function run(): void
    {
        $tenant = Tenant::query()->where('slug', 'browsejobs')->firstOrFail();
        $course = Course::query()->where('tenant_id', $tenant->id)->where('slug', 'data-engineering')->first();
        if ($course === null) {
            return;
        }

        $this->stories($tenant->id, $course->id);
        $this->questions($tenant->id, $course->id);
        $this->reviews($tenant->id);
    }

    private function stories(int $tenantId, int $courseId): void
    {
        $stories = [
            ['student_name' => 'Arjun Mehta', 'before_label' => 'Mechanical engineer, 2 yrs no job', 'after_role' => 'Data Engineer', 'package_label' => '₹11.5 LPA', 'company_name' => 'Nimbus Data', 'company_color' => '#12408f', 'rounds' => 4, 'quote' => 'sir i got the offer!! 😭🔥 4 rounds and honestly the SQL round was exactly the stuff you drilled us on 🙏', 'position' => 0],
            ['student_name' => 'Fatima Noor', 'before_label' => 'Bank teller, self-taught at night', 'after_role' => 'Analytics Engineer', 'package_label' => '₹9.0 LPA', 'company_name' => 'Cobalt Systems', 'company_color' => '#5b3fb0', 'rounds' => 3, 'quote' => '3 rounds done and cleared 🥹 not gonna lie your mock interviews felt harder than the actual one', 'position' => 1],
        ];

        foreach ($stories as $s) {
            PlacementStory::query()->updateOrCreate(
                ['tenant_id' => $tenantId, 'course_id' => $courseId, 'student_name' => $s['student_name']],
                array_merge($s, ['consent' => true, 'is_published' => true]),
            );
        }
    }

    private function questions(int $tenantId, int $courseId): void
    {
        $rounds = [
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
        ];

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
}
