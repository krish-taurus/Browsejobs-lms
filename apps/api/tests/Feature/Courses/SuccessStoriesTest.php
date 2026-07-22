<?php

declare(strict_types=1);

use App\Models\Course;
use App\Models\PlacementStory;
use App\Models\Tenant;

use function Pest\Laravel\getJson;

beforeEach(function () {
    $this->tenant = Tenant::factory()->domain('acme.test')->create();
    $this->course = withinTenant($this->tenant, fn () => Course::factory()->for($this->tenant)->create([
        'slug' => 'data-engineering', 'name' => 'Data Engineering',
    ]));
});

function story(Tenant $tenant, int $courseId, array $overrides = []): PlacementStory
{
    return withinTenant($tenant, fn () => PlacementStory::query()->create([
        'tenant_id' => $tenant->id, 'course_id' => $courseId,
        'student_name' => 'Demo Person', 'before_label' => 'Before', 'after_role' => 'Data Engineer',
        'package_label' => '₹10 LPA', 'company_name' => 'Acme', 'rounds' => 3, 'quote' => 'got it',
        'consent' => false, 'is_published' => true, 'is_sample' => true, 'position' => 0, ...$overrides,
    ]));
}

it('serves a published sample publicly and marks it as a sample', function () {
    story($this->tenant, $this->course->id, ['student_name' => 'Sample One']);

    getJson('http://acme.test/api/v1/courses/data-engineering/social-proof')
        ->assertOk()
        ->assertJsonCount(1, 'data.stories')
        ->assertJsonPath('data.stories.0.name', 'Sample One')
        ->assertJsonPath('data.stories.0.is_sample', true);
});

it('still hides an unpublished, unconsented, non-sample story', function () {
    story($this->tenant, $this->course->id, ['student_name' => 'Hidden', 'is_published' => false, 'is_sample' => false]);

    getJson('http://acme.test/api/v1/courses/data-engineering/social-proof')
        ->assertOk()
        ->assertJsonCount(0, 'data.stories');
});

it('lists home-page success stories across courses, real first then samples', function () {
    $de = $this->course;
    $da = withinTenant($this->tenant, fn () => Course::factory()->for($this->tenant)->create(['slug' => 'data-analytics', 'name' => 'Data Analytics']));

    story($this->tenant, $de->id, ['student_name' => 'A Sample', 'is_sample' => true, 'position' => 0]);
    story($this->tenant, $da->id, ['student_name' => 'A Real', 'is_sample' => false, 'consent' => true, 'position' => 0]);

    $res = getJson('http://acme.test/api/v1/success-stories')->assertOk()->assertJsonCount(2, 'data');
    // Real (consented) story sorts before the sample.
    $res->assertJsonPath('data.0.name', 'A Real')
        ->assertJsonPath('data.0.is_sample', false)
        ->assertJsonPath('data.0.course_slug', 'data-analytics')
        ->assertJsonPath('data.1.name', 'A Sample')
        ->assertJsonPath('data.1.is_sample', true);
});

it('never leaks another tenant\'s success stories', function () {
    story($this->tenant, $this->course->id, ['student_name' => 'Ours']);
    $other = Tenant::factory()->domain('other.test')->create();
    $oc = withinTenant($other, fn () => Course::factory()->for($other)->create(['slug' => 'data-engineering', 'name' => 'DE']));
    story($other, $oc->id, ['student_name' => 'Theirs']);

    getJson('http://acme.test/api/v1/success-stories')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.name', 'Ours');
});
