<?php

declare(strict_types=1);

namespace App\Actions\Employers;

use App\Enums\EmployerApplicationStage;
use App\Events\ApplicationStageChanged;
use App\Models\EmployerJobApplication;
use App\Models\User;
use App\Support\Audit\AuditLogger;
use App\Support\Tenancy\TenantContext;
use Illuminate\Validation\ValidationException;

/**
 * PRD-E F3/F5 — advance or reject an application. Every move writes a
 * timeline transition with actor attribution. Rejection requires a
 * reason (candidate-respectful, PRD-E F3).
 */
final readonly class MoveApplicationStage
{
    public function __construct(private AuditLogger $audit) {}

    public function handle(
        EmployerJobApplication $application,
        EmployerApplicationStage $target,
        User $actor,
        ?string $note = null,
        string $actorType = 'user',
    ): EmployerJobApplication {
        return app(TenantContext::class)->run($application->tenant, function () use ($application, $target, $actor, $note, $actorType): EmployerJobApplication {
            if (! $application->stage->canAdvanceTo($target)) {
                throw ValidationException::withMessages([
                    'stage' => "Cannot move a {$application->stage->value} application to {$target->value}.",
                ]);
            }

            if ($target === EmployerApplicationStage::Rejected && ($note === null || trim($note) === '')) {
                throw ValidationException::withMessages([
                    'note' => 'A rejection reason is required.',
                ]);
            }

            $from = $application->stage;

            $application->update([
                'stage' => $target->value,
                'rejection_reason' => $target === EmployerApplicationStage::Rejected ? $note : $application->rejection_reason,
            ]);

            $transition = $application->transitions()->create([
                'from_stage' => $from->value,
                'to_stage' => $target->value,
                'actor_type' => $actorType,
                'actor_id' => $actor->id,
                'note' => $note,
                'occurred_at' => now(),
            ]);

            $this->audit->log('employer.application_stage_changed', $application, [
                'from' => $from->value,
                'to' => $target->value,
            ], $actor);

            ApplicationStageChanged::dispatch($application->fresh(), $transition);

            return $application->fresh();
        });
    }
}
