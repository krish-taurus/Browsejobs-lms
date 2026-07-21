<?php

declare(strict_types=1);

namespace App\Actions\Curriculum;

use App\Events\CurriculumChanged;
use App\Models\Course;
use App\Models\Lesson;
use App\Models\Module;
use App\Models\Topic;
use Illuminate\Support\Facades\DB;

/**
 * Deep-copy a module — with all its topics and lessons — into another course,
 * so a syllabus shared across domains (e.g. a module used by both Data
 * Engineering and Data Analytics) doesn't have to be rebuilt by hand. The
 * copy is independent: later edits to either side don't affect the other.
 */
final readonly class CloneModuleIntoCourse
{
    public function handle(Module $source, Course $target): Module
    {
        return DB::transaction(function () use ($source, $target): Module {
            $source->load('topics.lessons');

            $module = Module::query()->create([
                'tenant_id' => $target->tenant_id,
                'course_id' => $target->id,
                'name' => $source->name,
                'position' => $target->modules()->count(),
            ]);

            foreach ($source->topics->sortBy('position')->values() as $ti => $topic) {
                $newTopic = Topic::query()->create([
                    'tenant_id' => $target->tenant_id,
                    'module_id' => $module->id,
                    'name' => $topic->name,
                    'position' => $ti,
                    'mock_enabled' => $topic->mock_enabled,
                ]);

                foreach ($topic->lessons->sortBy('position')->values() as $li => $lesson) {
                    Lesson::query()->create([
                        'tenant_id' => $target->tenant_id,
                        'topic_id' => $newTopic->id,
                        'title' => $lesson->title,
                        'type' => $lesson->type,
                        'position' => $li,
                    ]);
                }
            }

            CurriculumChanged::dispatch($target->id, (int) $target->tenant_id);

            return $module->load('topics.lessons');
        });
    }
}
