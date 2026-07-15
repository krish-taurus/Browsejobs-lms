<?php

declare(strict_types=1);

use App\Enums\LessonType;
use App\Models\Course;
use App\Models\Lesson;
use App\Models\Module;
use App\Models\Program;
use App\Models\Tenant;
use App\Models\Topic;
use Illuminate\Database\QueryException;

it('builds the programs -> courses -> modules -> topics -> lessons hierarchy', function () {
    $tenant = Tenant::factory()->create();

    withinTenant($tenant, function () {
        $program = Program::query()->create(['name' => 'Career Tracks', 'slug' => 'career-tracks']);
        $course = Course::query()->create([
            'program_id' => $program->id, 'code' => 'DE', 'name' => 'Data Engineering', 'slug' => 'de',
        ]);
        $module = Module::query()->create(['course_id' => $course->id, 'name' => 'Foundations']);
        $topic = Topic::query()->create(['module_id' => $module->id, 'name' => 'SQL']);
        Lesson::query()->create(['topic_id' => $topic->id, 'title' => 'Joins', 'type' => LessonType::LiveClass->value]);

        expect($course->program->name)->toBe('Career Tracks')
            ->and($course->modules->first()->topics->first()->lessons->first()->type)
            ->toBe(LessonType::LiveClass);
    });
});

it('scopes curriculum to its tenant', function () {
    $tenantA = Tenant::factory()->create();
    $tenantB = Tenant::factory()->create();

    withinTenant($tenantB, fn () => Course::query()->create([
        'code' => 'FS', 'name' => 'Full Stack', 'slug' => 'full-stack',
    ]));

    withinTenant($tenantA, function () {
        expect(Course::query()->count())->toBe(0);
    });
});

it('enforces unique course code per tenant', function () {
    $tenant = Tenant::factory()->create();

    withinTenant($tenant, function () {
        Course::query()->create(['code' => 'DE', 'name' => 'Data Eng', 'slug' => 'de']);

        expect(fn () => Course::query()->create(['code' => 'DE', 'name' => 'Dupe', 'slug' => 'dupe']))
            ->toThrow(QueryException::class);
    });
});
