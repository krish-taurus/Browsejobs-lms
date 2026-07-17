<?php

declare(strict_types=1);

namespace App\Actions\Content;

use App\Enums\SyllabusStatus;
use App\Jobs\RenderSyllabus;
use App\Models\Syllabus;
use App\Models\User;
use App\Support\Audit\AuditLogger;
use App\Support\Tenancy\TenantContext;
use Illuminate\Validation\ValidationException;

/**
 * Trainer approval of a course syllabus (PRD §6.2 / §7 human checkpoint). Approving
 * queues the branded render; only a rendered (hence approved) syllabus is downloadable.
 * Idempotent; audited. Mirrors ApproveQuiz. Clears the staleness flag and re-renders
 * with the current content — so re-approving after an edit/regeneration republishes.
 */
final readonly class ApproveSyllabus
{
    public function __construct(private AuditLogger $audit) {}

    public function handle(Syllabus $syllabus, ?User $actor = null): Syllabus
    {
        return app(TenantContext::class)->run($syllabus->tenant, function () use ($syllabus, $actor): Syllabus {
            // Fully done already (approved + rendered) — no-op.
            if ($syllabus->isApproved() && $syllabus->isRendered()) {
                return $syllabus;
            }

            if (blank($syllabus->content)) {
                throw ValidationException::withMessages(['content' => 'Generate or write the syllabus before approving.']);
            }

            $wasApproved = $syllabus->isApproved();

            $syllabus->update([
                'status' => SyllabusStatus::Approved->value,
                'approved_by' => $actor?->id ?? $syllabus->approved_by,
                'approved_at' => $syllabus->approved_at ?? now(),
                'is_stale' => false,
                // render_status is deliberately NOT reset to pending here: a previously
                // rendered artifact must keep serving (PRD §6.21 — running batches
                // unaffected) until RenderSyllabus overwrites it on success. On a first
                // approval it is still 'pending' until the render below writes the doc.
            ]);

            if (! $wasApproved) {
                $this->audit->log(
                    action: 'syllabus.approved',
                    target: $syllabus,
                    metadata: ['course_id' => $syllabus->course_id, 'version' => $syllabus->version],
                    actor: $actor,
                );
            }

            RenderSyllabus::dispatch($syllabus->id);

            return $syllabus->refresh();
        });
    }
}
