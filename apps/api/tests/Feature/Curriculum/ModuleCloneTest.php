<?php

declare(strict_types=1);

use App\Models\Course;
use App\Models\Lesson;
use App\Models\Module;
use App\Models\Tenant;
use App\Models\Topic;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Laravel\Sanctum\Sanctum;

use function Pest\Laravel\getJson;
use function Pest\Laravel\postJson;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
    $this->tenant = Tenant::factory()->create();
    $this->admin = User::factory()->for($this->tenant)->create(['user_type' => 'staff']);
    $this->admin->assignRole('admin'); // has manage-curriculum
    Sanctum::actingAs($this->admin);
});

/** A module with 2 topics, each with 2 lessons, inside a fresh course. */
function moduleWithContent(Tenant $tenant): Module
{
    $course = Course::factory()->for($tenant)->create();
    $module = Module::factory()->for($tenant)->create(['course_id' => $course->id, 'name' => 'ETL Foundations']);
    foreach (range(0, 1) as $t) {
        $topic = Topic::factory()->for($tenant)->create([
            'module_id' => $module->id, 'position' => $t, 'mock_enabled' => $t === 0,
        ]);
        foreach (range(0, 1) as $l) {
            Lesson::factory()->for($tenant)->create(['topic_id' => $topic->id, 'position' => $l]);
        }
    }

    return $module;
}

it('clones a module with all its topics and lessons into another course', function () {
    $module = moduleWithContent($this->tenant);
    $target = Course::factory()->for($this->tenant)->create();

    postJson("/api/v1/admin/modules/{$module->id}/clone", ['course_id' => $target->id])
        ->assertCreated()
        ->assertJsonPath('data.name', 'ETL Foundations')
        ->assertJsonPath('data.course_id', $target->id);

    $clone = Module::query()->where('course_id', $target->id)->firstOrFail();
    expect($clone->id)->not->toBe($module->id)
        ->and($clone->topics()->count())->toBe(2)
        ->and(Lesson::query()->whereIn('topic_id', $clone->topics()->pluck('id'))->count())->toBe(4);

    // The mock_enabled flag carries over per topic.
    expect($clone->topics()->orderBy('position')->first()->mock_enabled)->toBeTrue();

    // The original is untouched.
    expect($module->topics()->count())->toBe(2);
});

it('search finds tenant modules by name', function () {
    moduleWithContent($this->tenant);

    getJson('/api/v1/admin/modules/search?q=ETL')->assertOk()
        ->assertJsonPath('data.0.name', 'ETL Foundations')
        ->assertJsonPath('data.0.topics_count', 2);

    getJson('/api/v1/admin/modules/search?q=nonexistent')->assertOk()
        ->assertJsonCount(0, 'data');
});

it('cannot clone or see another tenant module (cross-tenant denial)', function () {
    $other = Tenant::factory()->create();
    $foreignModule = moduleWithContent($other);
    $myCourse = Course::factory()->for($this->tenant)->create();

    // Not visible in this tenant's search…
    getJson('/api/v1/admin/modules/search?q=ETL')->assertOk()->assertJsonCount(0, 'data');

    // …and cannot be cloned (route binding is tenant-scoped → 404).
    postJson("/api/v1/admin/modules/{$foreignModule->id}/clone", ['course_id' => $myCourse->id])
        ->assertNotFound();
});

it('cannot clone into another tenant course', function () {
    $module = moduleWithContent($this->tenant);
    $foreignCourse = Course::factory()->for(Tenant::factory()->create())->create();

    postJson("/api/v1/admin/modules/{$module->id}/clone", ['course_id' => $foreignCourse->id])
        ->assertNotFound();
});

it('denies staff without manage-curriculum', function () {
    $mentor = User::factory()->for($this->tenant)->create(['user_type' => 'staff']);
    $mentor->assignRole('mentor');
    Sanctum::actingAs($mentor);

    getJson('/api/v1/admin/modules/search?q=x')->assertForbidden();
});
