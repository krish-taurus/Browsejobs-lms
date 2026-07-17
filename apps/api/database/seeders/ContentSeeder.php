<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Actions\Content\ApproveNotes;
use App\Enums\LessonType;
use App\Enums\NoteSource;
use App\Enums\NoteStatus;
use App\Models\Lesson;
use App\Models\LessonNote;
use App\Models\Tenant;
use App\Models\Topic;
use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Illuminate\Database\Seeder;

/**
 * Makes the content pipeline (P3.5b) demo-able: attaches a transcript + approved notes
 * to a notes lesson, which feeds the transcript to the AI Tutor knowledge base so the
 * tutor can cite class content and the student /notes page has data.
 */
class ContentSeeder extends Seeder
{
    public function run(): void
    {
        $tenant = Tenant::query()->where('slug', 'browsejobs')->first();
        if ($tenant === null) {
            return;
        }

        app(TenantContext::class)->run($tenant, function () use ($tenant): void {
            $lesson = Lesson::query()->where('type', LessonType::Notes->value)->orderBy('id')->first();
            if ($lesson === null) {
                $topic = Topic::query()->orderBy('id')->first();
                if ($topic === null) {
                    return;
                }
                $lesson = Lesson::query()->create([
                    'tenant_id' => $tenant->id, 'topic_id' => $topic->id,
                    'title' => 'Intro to functions — class notes', 'type' => LessonType::Notes->value, 'position' => 97,
                ]);
            }

            if (LessonNote::query()->where('lesson_id', $lesson->id)->exists()) {
                return;
            }

            $staff = User::query()->where('user_type', 'staff')->first();

            $note = LessonNote::query()->create([
                'tenant_id' => $tenant->id,
                'lesson_id' => $lesson->id,
                'transcript' => 'In today\'s class we defined functions in Python with the def keyword. '
                    .'A function groups reusable logic and can return a value. We covered parameters, '
                    .'default arguments, and why a base case matters when a function calls itself (recursion).',
                'notes' => "## Key concepts\n\n- Functions group reusable logic; `def` defines them.\n"
                    ."- Parameters pass data in; `return` sends a value back.\n\n"
                    ."## Key takeaways\n\n- Give functions one clear job.\n- Recursion needs a base case.",
                'source' => NoteSource::Paste->value,
                'status' => NoteStatus::Draft->value,
            ]);

            // Approve → publishes notes to students + feeds the transcript to the tutor KB.
            app(ApproveNotes::class)->handle($note, $staff);
        });
    }
}
