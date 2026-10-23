<?php

declare(strict_types=1);

namespace App\Support\Employers\Agent;

use App\Actions\Employers\ReadHiringIntent;
use App\Actions\Employers\ReviseJobDescription;
use App\Models\EmployerAgentAction;
use App\Models\EmployerJob;
use App\Models\User;
use Illuminate\Validation\ValidationException;

/**
 * "Make the Data Engineer role 4 to 7 years", "3 openings", "add Kafka", "Pune instead": a change to a
 * role that is already posted. The words are read into field changes, and the client approves the exact
 * list before anything is saved, like every other proposal.
 */
final readonly class JobUpdatePlanner
{
    public function __construct(
        private ReadHiringIntent $intent,
        private ReviseJobDescription $reviseJd,
    ) {}

    public function plan(EmployerJob $job, User $user, string $text): PlannedAction|string
    {
        $changes = [];
        $lines = [];

        try {
            $intent = $this->intent->handle($user, $text);
        } catch (ValidationException) {
            $intent = null;
        }

        if ($intent !== null) {
            foreach (['experience_min_years' => 'minimum experience', 'experience_max_years' => 'maximum experience', 'openings' => 'openings'] as $field => $label) {
                if ($intent[$field] !== null && $intent[$field] !== $job->{$field}) {
                    $changes[$field] = $intent[$field];
                    $lines[] = "{$label} {$intent[$field]}";
                }
            }
            if ($intent['locations'] !== [] && $intent['locations'] !== ($job->locations ?? [])) {
                $changes['locations'] = $intent['locations'];
                $changes['remote'] = false;
                $lines[] = 'location '.implode(', ', $intent['locations']);
            }
            if ($intent['remote'] && ! $job->remote) {
                $changes['remote'] = true;
                $lines[] = 'remote';
            }
        }

        try {
            $revised = $this->reviseJd->handle($user, $job->title, (string) $job->description, array_values((array) ($job->skills ?? [])), $text);
            $added = array_values(array_udiff($revised['skills'], (array) ($job->skills ?? []), 'strcasecmp'));
            $removed = array_values(array_udiff((array) ($job->skills ?? []), $revised['skills'], 'strcasecmp'));
            if ($added !== [] || $removed !== []) {
                $changes['skills'] = $revised['skills'];
                $lines[] = 'skills'.($added !== [] ? ' +'.implode(', +', $added) : '').($removed !== [] ? ' −'.implode(', −', $removed) : '');
            }
            if (trim($revised['description']) !== trim((string) $job->description) && ($added !== [] || $removed !== [] || $changes === [])) {
                $changes['description'] = $revised['description'];
                $lines[] = 'description reworded';
            }
        } catch (ValidationException) {
            // The field changes above still stand.
        }

        if ($changes === []) {
            return 'I could not tell what to change on *'.$job->title.'*. Tell me, for example: "make it 4 to 7 years", "3 openings", "add Kafka to the skills", "Pune instead".';
        }

        return new PlannedAction(
            EmployerAgentAction::KIND_UPDATE_JOB,
            $job->id,
            ['changes' => $changes],
            "Update *{$job->title}*: ".implode('; ', $lines).'. The role stays live.',
        );
    }
}
