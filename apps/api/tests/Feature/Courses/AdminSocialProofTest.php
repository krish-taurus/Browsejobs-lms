<?php

declare(strict_types=1);

use App\Models\Course;
use App\Models\CourseInterviewQuestion;
use App\Models\PlacementStory;
use App\Models\Review;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;

use function Pest\Laravel\deleteJson;
use function Pest\Laravel\getJson;
use function Pest\Laravel\postJson;
use function Pest\Laravel\putJson;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
    $this->tenant = Tenant::factory()->create();
    $this->admin = User::factory()->for($this->tenant)->create(['user_type' => 'staff']);
    $this->admin->assignRole('admin');
    $this->course = withinTenant($this->tenant, fn () => Course::factory()->for($this->tenant)->create([
        'slug' => 'data-engineering', 'name' => 'Data Engineering',
    ]));
});

function storyBody(array $o = []): array
{
    return [
        'student_name' => 'Arjun Mehta', 'before_label' => 'Mechanical engineer', 'after_role' => 'Data Engineer',
        'package_label' => '₹11.5 LPA', 'company_name' => 'Nimbus Data', 'rounds' => 4, 'quote' => 'got the offer!',
        'consent' => true, 'is_published' => true, ...$o,
    ];
}

it('creates, lists, updates and deletes a placement story', function () {
    Sanctum::actingAs($this->admin);

    $id = postJson("/api/v1/admin/courses/{$this->course->id}/placement-stories", storyBody())
        ->assertCreated()->assertJsonPath('data.student_name', 'Arjun Mehta')->json('data.id');

    getJson("/api/v1/admin/courses/{$this->course->id}/placement-stories")
        ->assertOk()->assertJsonCount(1, 'data');

    putJson("/api/v1/admin/placement-stories/{$id}", storyBody(['package_label' => '₹13 LPA']))
        ->assertOk()->assertJsonPath('data.package_label', '₹13 LPA');

    deleteJson("/api/v1/admin/placement-stories/{$id}")->assertOk();
    expect(PlacementStory::withoutGlobalScopes()->count())->toBe(0);
});

it('uploads a WhatsApp screenshot for a story', function () {
    Storage::fake('s3');
    Sanctum::actingAs($this->admin);

    $id = postJson("/api/v1/admin/courses/{$this->course->id}/placement-stories", storyBody())->json('data.id');

    postJson("/api/v1/admin/placement-stories/{$id}/screenshot", [
        'image' => UploadedFile::fake()->image('offer.jpg', 400, 800),
    ])->assertOk()->assertJsonPath('data.has_screenshot', true);

    $path = PlacementStory::withoutGlobalScopes()->find($id)->screenshot_path;
    expect($path)->toContain("placement-stories/{$this->tenant->id}");
    Storage::disk('s3')->assertExists($path);
});

it('bulk-replaces the interview-question bank for a course', function () {
    Sanctum::actingAs($this->admin);

    putJson("/api/v1/admin/courses/{$this->course->id}/interview-questions", ['rounds' => [
        ['round_no' => 1, 'round_name' => 'Screening', 'questions' => ['Tell me about a pipeline.', '2nd highest salary?']],
        ['round_no' => 2, 'round_name' => 'SQL', 'questions' => ['RANK vs DENSE_RANK?']],
    ]])->assertOk()->assertJsonCount(2, 'data')->assertJsonCount(2, 'data.0.questions');

    // Re-save replaces (not appends).
    putJson("/api/v1/admin/courses/{$this->course->id}/interview-questions", ['rounds' => [
        ['round_no' => 1, 'round_name' => 'Screening', 'questions' => ['Only one now.']],
    ]])->assertOk()->assertJsonCount(1, 'data');

    expect(CourseInterviewQuestion::withoutGlobalScopes()->where('course_id', $this->course->id)->count())->toBe(1);
});

it('adds a course-tagged review', function () {
    Sanctum::actingAs($this->admin);

    postJson("/api/v1/admin/courses/{$this->course->id}/course-reviews", [
        'author_name' => 'Chakravarthi', 'author_meta' => '2 reviews', 'rating' => 5,
        'source' => 'google', 'body' => 'Grateful to BrowseJobs.',
    ])->assertCreated();

    $review = Review::withoutGlobalScopes()->where('author_name', 'Chakravarthi')->first();
    expect($review->course_slug)->toBe('data-engineering')->and($review->is_active)->toBeTrue();
});

it('denies staff without manage-curriculum', function () {
    $mentor = User::factory()->for($this->tenant)->create(['user_type' => 'staff']);
    $mentor->assignRole('mentor');
    Sanctum::actingAs($mentor);

    postJson("/api/v1/admin/courses/{$this->course->id}/placement-stories", storyBody())->assertForbidden();
    putJson("/api/v1/admin/courses/{$this->course->id}/interview-questions", ['rounds' => []])->assertForbidden();
});

it('404s a story in another tenant', function () {
    $other = Tenant::factory()->create();
    $foreign = withinTenant($other, function () use ($other) {
        $course = Course::factory()->for($other)->create(['slug' => 'x', 'name' => 'X']);

        return PlacementStory::query()->create([
            'tenant_id' => $other->id, 'course_id' => $course->id, 'student_name' => 'Theirs',
            'before_label' => 'a', 'after_role' => 'b',
        ]);
    });

    Sanctum::actingAs($this->admin);
    putJson("/api/v1/admin/placement-stories/{$foreign->id}", storyBody())->assertNotFound();
});
