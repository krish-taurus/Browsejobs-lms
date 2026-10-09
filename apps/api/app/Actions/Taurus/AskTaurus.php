<?php

declare(strict_types=1);

namespace App\Actions\Taurus;

use App\Enums\AiPurpose;
use App\Models\TaurusWorkspace;
use App\Models\User;
use App\Services\AI\AiGateway;
use App\Support\Taurus\TaurusClock;
use App\Support\Taurus\WorkspaceBrain;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;

/**
 * "Ask Taurus" (ADR 0052): a spoken question about the floor, answered by the
 * brain from a compact snapshot of live state. The brain only ever reads —
 * approvals stay on the buttons, and the prompt forbids claiming otherwise.
 */
final class AskTaurus
{
    private const PROMPT = 'taurus_ask';

    private const PROMPT_VERSION = 1;

    public function __construct(
        private readonly BuildFloorState $state,
        private readonly WorkspaceBrain $brain,
        private readonly AiGateway $gateway,
    ) {}

    /**
     * @return array{answer: string, provider: string, model: string}
     *
     * @throws BrainNotConfigured when the workspace has no usable brain
     */
    public function handle(User $user, TaurusWorkspace $workspace, string $question, string $floor): array
    {
        // A client workspace resolves to its own key or nothing — never the platform's.
        $active = $this->brain->resolve($workspace);
        if ($active === null) {
            throw new BrainNotConfigured;
        }

        $result = $this->gateway->complete($user, AiPurpose::Taurus, self::PROMPT, self::PROMPT_VERSION, [
            'state' => $this->summary($this->state->handle($workspace, $floor)),
            'question' => trim($question),
        ], ['max_tokens' => 220, ...$active['opts']]);

        return [
            'answer' => trim($result->text),
            'provider' => $active['provider'],
            'model' => $result->model,
        ];
    }

    /**
     * The floor as short plain lines — only facts the console itself shows,
     * so every number the brain can quote is one the founder can see.
     *
     * @param  array<string, mixed>  $state
     */
    public function summary(array $state): string
    {
        $local = CarbonImmutable::now(TaurusClock::timezone());
        $kpis = $state['kpis'];

        $lines = [];
        $lines[] = "Floor: {$state['floor']}. Local time: {$local->format('H:i')} ({$local->format('D j M')}, ".TaurusClock::timezone().').';
        $lines[] = "Agents: {$kpis['agents']} total, {$kpis['online']} online, {$kpis['running']} working or thinking.";

        foreach (array_slice($state['agents'], 0, 30) as $agent) {
            $line = "- {$agent['name']}";
            if (! empty($agent['zone'])) {
                $line .= " ({$agent['zone']})";
            }
            $line .= ": {$agent['status']}";
            if (! empty($agent['task'])) {
                $line .= ' — on "'.Str::limit((string) $agent['task'], 80).'"';
                if ((float) $agent['progress'] > 0) {
                    $line .= ' at '.(int) round(((float) $agent['progress']) * 100).'%';
                }
            }
            $lines[] = $line.'.';
        }

        $open = array_values(array_filter($state['tasks'], static fn (array $t): bool => in_array($t['status'], ['queued', 'running', 'needs_approval'], true)));
        $waiting = array_values(array_filter($open, static fn (array $t): bool => $t['status'] === 'needs_approval'));

        $lines[] = "Approvals waiting: {$kpis['needs']}.";
        foreach (array_slice($waiting, 0, 10) as $task) {
            $risk = $task['risk'] !== null ? ", {$task['risk']} risk" : '';
            $lines[] = '- '.($task['agent_name'] ?? 'An agent').' wants to: '.Str::limit((string) ($task['approval_action'] ?: $task['title']), 120)."{$risk}.";
        }

        $lines[] = 'Open tasks: '.count($open).'.';
        foreach (array_slice(array_filter($open, static fn (array $t): bool => $t['status'] !== 'needs_approval'), 0, 15) as $task) {
            $lines[] = '- '.($task['agent_name'] ?? 'An agent').': "'.Str::limit((string) $task['title'], 80)."\" ({$task['status']}).";
        }

        $lines[] = "Done today: {$kpis['done_today']}. Failed today: {$kpis['failed_today']}.";
        foreach (array_slice(array_filter($state['tasks'], static fn (array $t): bool => $t['status'] === 'failed'), 0, 5) as $task) {
            $lines[] = '- Failed: "'.Str::limit((string) $task['title'], 80).'" ('.($task['agent_name'] ?? 'an agent').').';
        }

        $spend = $state['spend']['today'];
        if ($spend === []) {
            $lines[] = 'Spend today: nothing reported.';
        } else {
            $lines[] = 'Spend today, exactly as the bots reported it:';
            foreach ($spend as $row) {
                $parts = [];
                if ($row['amount'] !== null) {
                    $parts[] = self::number($row['amount']).($row['currency'] ? " {$row['currency']}" : '');
                }
                if ($row['units'] !== null) {
                    $parts[] = self::number($row['units']).' '.($row['unit_label'] ?? 'units');
                }
                $lines[] = "- {$row['source']}: ".($parts !== [] ? implode(', ', $parts) : 'no amount reported').'.';
            }
        }

        return implode("\n", $lines);
    }

    private static function number(float|int $value): string
    {
        $formatted = number_format((float) $value, 4, '.', '');

        return rtrim(rtrim($formatted, '0'), '.');
    }
}
