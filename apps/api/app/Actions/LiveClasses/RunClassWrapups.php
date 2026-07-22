<?php

declare(strict_types=1);

namespace App\Actions\LiveClasses;

use App\Enums\BatchMemberStatus;
use App\Enums\LiveSessionStatus;
use App\Models\BatchMember;
use App\Models\Flashcard;
use App\Models\InAppNotification;
use App\Models\Lesson;
use App\Models\LiveSession;
use App\Models\Tenant;
use App\Support\Messaging\Messenger;
use App\Support\Tenancy\TenantContext;

/**
 * Post-class study nudge (PRD §6 daily loop, "Recall" step). Once a class has
 * ended and its flashcards are published, nudge the whole cohort — attendees and
 * absentees — to review that class's flashcards. Absentees benefit most: the
 * nudge is their catch-up prompt.
 *
 * Fires only when the material actually exists, so it never sends an empty pack;
 * a class with no flashcards is simply left for a later run (until it falls out
 * of the look-back window). Idempotent: `wrapped_up_at` is the dedup, so a class
 * is wrapped exactly once. Run hourly by `class:wrapup`.
 */
final readonly class RunClassWrapups
{
    /** Only look back this far — older un-wrapped classes are let go. */
    private const LOOKBACK_DAYS = 14;

    /** Grace after start before a class counts as "ended enough" to wrap. */
    private const MIN_AGE_HOURS = 1;

    public function __construct(private Messenger $messenger) {}

    /** @return int number of classes wrapped */
    public function handle(): int
    {
        $wrapped = 0;

        LiveSession::query()->withoutGlobalScopes()
            ->whereNull('wrapped_up_at')
            ->whereNotNull('topic_id')
            ->where('status', '!=', LiveSessionStatus::Cancelled->value)
            ->where('scheduled_start', '<=', now()->subHours(self::MIN_AGE_HOURS))
            ->where('scheduled_start', '>=', now()->subDays(self::LOOKBACK_DAYS))
            ->get()
            ->each(function (LiveSession $session) use (&$wrapped): void {
                $tenant = Tenant::query()->find($session->tenant_id);
                if ($tenant === null) {
                    return;
                }

                if (app(TenantContext::class)->run($tenant, fn (): bool => $this->wrapOne($session))) {
                    $wrapped++;
                }
            });

        return $wrapped;
    }

    /** Wrap a single class if its material is ready. Returns true when it sent. */
    private function wrapOne(LiveSession $session): bool
    {
        $lessonId = Flashcard::query()
            ->whereIn('lesson_id', Lesson::query()->where('topic_id', $session->topic_id)->select('id'))
            ->orderBy('lesson_id')
            ->value('lesson_id');

        if ($lessonId === null) {
            return false; // material not ready yet — try again next run
        }

        $link = rtrim((string) config('app.frontend_url', ''), '/')."/flashcards/{$lessonId}";

        $members = BatchMember::query()
            ->where('batch_id', $session->batch_id)
            ->whereIn('status', array_map(fn (BatchMemberStatus $s) => $s->value, BatchMemberStatus::occupying()))
            ->with('student')
            ->get();

        foreach ($members as $member) {
            $student = $member->student;
            if ($student === null) {
                continue;
            }

            $this->messenger->send($student, 'class_wrapup', [
                'name' => $student->name,
                'title' => $session->title,
                'link' => $link,
            ]);

            InAppNotification::query()->create([
                'tenant_id' => $session->tenant_id,
                'user_id' => $student->id,
                'title' => "Review today's class — {$session->title}.",
                'body' => 'Your flashcards are ready. A few minutes of recall now is worth an hour of re-reading later.',
                'url' => "/flashcards/{$lessonId}",
            ]);
        }

        $session->update(['wrapped_up_at' => now()]);

        return true;
    }
}
