<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Actions\Content\GenerateNotes;
use App\Enums\NoteStatus;
use App\Models\LessonNote;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

/**
 * Queued AI notes generation (PRD §6.10; every AI call is a queued job per CLAUDE.md).
 * Skips an already-approved note (never overwrites approved notes). A failure leaves the
 * note as a draft for manual authoring. Bills the generating staff user.
 */
final class GenerateNotesJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(
        public readonly int $lessonNoteId,
        public readonly int $actorUserId,
    ) {}

    public function handle(GenerateNotes $generate): void
    {
        $note = LessonNote::query()->withoutGlobalScopes()->find($this->lessonNoteId);
        if ($note === null || $note->status === NoteStatus::Approved) {
            return;
        }

        $tenant = Tenant::query()->find($note->tenant_id);
        $actor = User::query()->withoutGlobalScopes()->find($this->actorUserId);
        if ($tenant === null || $actor === null) {
            return;
        }

        app(TenantContext::class)->run($tenant, function () use ($note, $actor, $generate): void {
            try {
                $generate->handle($note, $actor);
            } catch (Throwable) {
                // Draft left intact; the trainer retries or authors by hand.
            }
        });
    }
}
