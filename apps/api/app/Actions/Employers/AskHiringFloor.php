<?php

declare(strict_types=1);

namespace App\Actions\Employers;

use App\Actions\Taurus\BrainNotConfigured;
use App\Enums\AiPurpose;
use App\Models\EmployerJob;
use App\Models\EmployerWorkspace;
use App\Models\User;
use App\Services\AI\AiGateway;
use App\Support\Taurus\BrainStatus;
use App\Support\Taurus\TaurusClock;
use Carbon\CarbonImmutable;

/**
 * "Ask Taurus" for an employer (ADR 0052): a spoken question about their own
 * hiring floor, answered by the Taurus brain from the same aggregates the
 * hiring-floor endpoint shows. The summary carries stage counts, scores,
 * bot activity and the title-only feed — never a candidate's name or contact
 * details — and the brain may describe but never act.
 */
final class AskHiringFloor
{
    private const PROMPT = 'taurus_hiring_ask';

    private const PROMPT_VERSION = 1;

    public function __construct(
        private readonly BuildHiringFloor $floor,
        private readonly BrainStatus $brain,
        private readonly AiGateway $gateway,
    ) {}

    /**
     * @return array{answer: string, provider: string, model: string}
     *
     * @throws BrainNotConfigured when no LLM has a key
     */
    public function handle(User $user, EmployerWorkspace $workspace, ?EmployerJob $job, string $question): array
    {
        $active = $this->brain->active();
        if ($active === null) {
            throw new BrainNotConfigured;
        }

        $result = $this->gateway->complete($user, AiPurpose::Taurus, self::PROMPT, self::PROMPT_VERSION, [
            'floor' => $this->summary($this->floor->handle($workspace, $job), $job),
            'question' => trim($question),
        ], ['max_tokens' => 220, ...BrainStatus::gatewayOptions($active)]);

        return [
            'answer' => trim($result->text),
            'provider' => $active['provider'],
            'model' => $result->model,
        ];
    }

    /**
     * The floor as short plain lines. Built only from BuildHiringFloor's
     * output, which is aggregate- and title-level by construction.
     *
     * @param  array<string, mixed>  $floor
     */
    public function summary(array $floor, ?EmployerJob $job): string
    {
        $local = CarbonImmutable::now(TaurusClock::timezone());
        $kpis = $floor['kpis'];

        $lines = [];
        $lines[] = ($job !== null ? "Scope: one role — {$job->title}." : 'Scope: every role in this workspace.')
            ." Local time: {$local->format('H:i')} ({$local->format('D j M')}).";
        $lines[] = "Open roles: {$kpis['open_roles']}. In pipeline: {$kpis['in_pipeline']}. Interviews in flight: {$kpis['interviews_in_flight']}. Offers out: {$kpis['offers']}. Hired: {$kpis['hired']}.";

        $lines[] = 'Stages (candidates there now):';
        foreach ($floor['stages'] as $stage) {
            $line = "- {$stage['label']}: {$stage['count']}";
            if ($stage['avg_score'] !== null) {
                $line .= ", avg score {$stage['avg_score']}";
            }
            if ($stage['avg_days_in_stage'] !== null) {
                $line .= ", avg {$stage['avg_days_in_stage']} days in stage";
            }
            $line .= ", {$stage['moved_today']} moved in today";
            $lines[] = $line.'.';
        }

        $lines[] = 'Bots:';
        foreach ($floor['bots'] as $bot) {
            $metrics = array_map(
                static fn (array $m): string => $m['label'].' '.($m['value'] ?? 'unknown'),
                $bot['metrics'],
            );
            $lines[] = "- {$bot['name']} ({$bot['status']}): {$bot['task']} ".implode(', ', $metrics).'.';
        }

        if ($floor['events'] === []) {
            $lines[] = 'Recent activity: none yet.';
        } else {
            $lines[] = 'Recent activity, newest first:';
            foreach (array_slice($floor['events'], 0, 12) as $event) {
                $at = CarbonImmutable::parse($event['occurred_at'])->setTimezone(TaurusClock::timezone());
                $lines[] = "- {$at->format('D H:i')}: {$event['message']} ({$event['actor']}).";
            }
        }

        return implode("\n", $lines);
    }
}
