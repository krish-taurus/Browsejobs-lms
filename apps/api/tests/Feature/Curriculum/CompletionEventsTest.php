<?php

declare(strict_types=1);

use App\Actions\Curriculum\MarkTopicCompleted;
use App\Events\ModuleCompleted;
use App\Events\TopicCompleted;
use App\Models\Course;
use App\Models\Module;
use App\Models\Tenant;
use App\Models\Topic;
use App\Models\User;
use Illuminate\Support\Facades\Event;

beforeEach(function () {
    $this->tenant = Tenant::factory()->create();
    $this->student = User::factory()->for($this->tenant)->create(['user_type' => 'student']);

    [$this->module, $this->topicA, $this->topicB] = withinTenant($this->tenant, function () {
        $course = Course::query()->create(['code' => 'DE', 'name' => 'Data Eng', 'slug' => 'de']);
        $module = Module::query()->create(['course_id' => $course->id, 'name' => 'Foundations']);

        return [
            $module,
            Topic::query()->create(['module_id' => $module->id, 'name' => 'SQL']),
            Topic::query()->create(['module_id' => $module->id, 'name' => 'Python']),
        ];
    });
});

it('fires TopicCompleted and records the completion', function () {
    Event::fake([TopicCompleted::class, ModuleCompleted::class]);

    withinTenant($this->tenant, fn () => app(MarkTopicCompleted::class)->handle($this->student, $this->topicA));

    Event::assertDispatched(TopicCompleted::class);
    Event::assertNotDispatched(ModuleCompleted::class);

    $this->assertDatabaseHas('topic_completions', [
        'user_id' => $this->student->id, 'topic_id' => $this->topicA->id,
    ]);
});

it('fires ModuleCompleted only once every topic in the module is done', function () {
    Event::fake([ModuleCompleted::class]);

    withinTenant($this->tenant, function () {
        $mark = app(MarkTopicCompleted::class);
        $mark->handle($this->student, $this->topicA);
        Event::assertNotDispatched(ModuleCompleted::class);

        $mark->handle($this->student, $this->topicB);
    });

    Event::assertDispatched(ModuleCompleted::class, fn (ModuleCompleted $e) => $e->module->is($this->module));
});

it('is idempotent: re-marking a topic fires nothing', function () {
    $mark = app(MarkTopicCompleted::class);
    withinTenant($this->tenant, fn () => $mark->handle($this->student, $this->topicA));

    Event::fake([TopicCompleted::class]);
    withinTenant($this->tenant, fn () => $mark->handle($this->student, $this->topicA));

    Event::assertNotDispatched(TopicCompleted::class);
});
