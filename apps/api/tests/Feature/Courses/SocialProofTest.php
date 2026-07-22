<?php

declare(strict_types=1);

use App\Models\Course;
use App\Models\CourseInterviewQuestion;
use App\Models\PlacementStory;
use App\Models\Review;
use App\Models\Tenant;

use function Pest\Laravel\getJson;

beforeEach(function () {
    $this->tenant = Tenant::factory()->domain('acme.test')->create();
    $this->course = withinTenant($this->tenant, fn () => Course::factory()->for($this->tenant)->create([
        'slug' => 'data-engineering', 'name' => 'Data Engineering',
    ]));
});

function aStory(Tenant $tenant, int $courseId, array $overrides = []): PlacementStory
{
    return withinTenant($tenant, fn () => PlacementStory::query()->create([
        'tenant_id' => $tenant->id, 'course_id' => $courseId,
        'student_name' => 'Arjun Mehta', 'before_label' => 'Mechanical engineer', 'after_role' => 'Data Engineer',
        'package_label' => '₹11.5 LPA', 'company_name' => 'Nimbus Data', 'rounds' => 4, 'quote' => 'got the offer!!',
        'consent' => true, 'is_published' => true, 'position' => 0, ...$overrides,
    ]));
}

function aQuestion(Tenant $tenant, int $courseId, int $round, string $q, int $pos = 0): CourseInterviewQuestion
{
    return withinTenant($tenant, fn () => CourseInterviewQuestion::query()->create([
        'tenant_id' => $tenant->id, 'course_id' => $courseId, 'round_no' => $round,
        'round_name' => "Round {$round}", 'question' => $q, 'is_published' => true, 'position' => $pos,
    ]));
}

function aCourseReview(Tenant $tenant, string $slug, array $overrides = []): Review
{
    return withinTenant($tenant, fn () => Review::query()->create([
        'tenant_id' => $tenant->id, 'author_name' => 'Chakravarthi', 'author_meta' => '2 reviews',
        'course_slug' => $slug, 'rating' => 5, 'body' => 'Grateful to BrowseJobs.', 'source' => 'google', 'is_active' => true,
        ...$overrides,
    ]));
}

it('returns published + consented stories, questions grouped by round, and course reviews', function () {
    aStory($this->tenant, $this->course->id);
    aStory($this->tenant, $this->course->id, ['student_name' => 'Hidden A', 'is_published' => false]); // unpublished
    aStory($this->tenant, $this->course->id, ['student_name' => 'Hidden B', 'consent' => false]);      // no consent

    aQuestion($this->tenant, $this->course->id, 1, 'Walk me through a pipeline.', 0);
    aQuestion($this->tenant, $this->course->id, 1, '2nd highest salary in SQL.', 1);
    aQuestion($this->tenant, $this->course->id, 2, 'RANK vs DENSE_RANK.', 0);

    aCourseReview($this->tenant, 'data-engineering');
    aCourseReview($this->tenant, 'data-engineering', ['author_name' => 'Inactive', 'is_active' => false]); // hidden
    aCourseReview($this->tenant, 'data-analytics', ['author_name' => 'Other course']);                    // other course

    getJson('http://acme.test/api/v1/courses/data-engineering/social-proof')
        ->assertOk()
        ->assertJsonPath('data.course.slug', 'data-engineering')
        ->assertJsonCount(1, 'data.stories')
        ->assertJsonPath('data.stories.0.name', 'Arjun Mehta')
        ->assertJsonPath('data.stories.0.package', '₹11.5 LPA')
        ->assertJsonCount(2, 'data.question_rounds')          // 2 rounds
        ->assertJsonCount(2, 'data.question_rounds.0.questions') // round 1 has 2
        ->assertJsonPath('data.question_rounds.0.round_no', 1)
        ->assertJsonCount(1, 'data.reviews')                 // active + this course only
        ->assertJsonPath('data.reviews.0.source', 'google')
        ->assertJsonPath('data.reviews.0.meta', '2 reviews');
});

it('404s an unknown course slug', function () {
    getJson('http://acme.test/api/v1/courses/nope/social-proof')->assertNotFound();
});

it('never leaks another tenant\'s stories (course resolved within the host tenant)', function () {
    aStory($this->tenant, $this->course->id); // ours

    $other = Tenant::factory()->domain('other.test')->create();
    $otherCourse = withinTenant($other, fn () => Course::factory()->for($other)->create(['slug' => 'data-engineering', 'name' => 'DE']));
    aStory($other, $otherCourse->id, ['student_name' => 'Not ours', 'package_label' => '₹99 LPA']);

    // acme host only ever sees acme's single story.
    getJson('http://acme.test/api/v1/courses/data-engineering/social-proof')
        ->assertOk()
        ->assertJsonCount(1, 'data.stories')
        ->assertJsonPath('data.stories.0.name', 'Arjun Mehta');
});
