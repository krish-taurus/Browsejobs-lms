<?php

declare(strict_types=1);

namespace App\Actions\Engagement;

use App\Enums\AiPurpose;
use App\Models\Celebration;
use App\Models\CelebrationGuidance;
use App\Models\User;
use App\Services\AI\AiGateway;
use App\Support\Scoring\ScoreCalculator;
use Throwable;

/**
 * The "Your Path to the Same" card (PRD §6.18): three concrete actions built
 * from the RECIPIENT's mastery gaps, cached per (celebration, user) so the AI
 * cost is paid once. Fails safe to deterministic actions from the same gaps —
 * concrete tips, never generic cheerleading.
 */
final readonly class GenerateGapGuidance
{
    public function __construct(
        private AiGateway $gateway,
        private ScoreCalculator $scores,
    ) {}

    public function handle(Celebration $celebration, User $recipient): CelebrationGuidance
    {
        $existing = CelebrationGuidance::query()->withoutGlobalScopes()
            ->where('celebration_id', $celebration->id)
            ->where('user_id', $recipient->id)
            ->first();

        if ($existing !== null) {
            return $existing;
        }

        $picture = $this->scores->computeFor($recipient);
        /** @var list<string> $needsWork */
        $needsWork = $picture['needs_work'];
        /** @var list<array{name: string, pct: int, band: string}> $mastery */
        $mastery = $picture['mastery'];

        [$intro, $actions, $source] = $this->narrate($celebration, $recipient, $needsWork, $mastery);

        return CelebrationGuidance::query()->create([
            'tenant_id' => $celebration->tenant_id,
            'celebration_id' => $celebration->id,
            'user_id' => $recipient->id,
            'intro' => $intro,
            'actions' => $actions,
            'source' => $source,
        ]);
    }

    /**
     * @param  list<string>  $needsWork
     * @param  list<array{name: string, pct: int, band: string}>  $mastery
     * @return array{0: string, 1: list<string>, 2: string}
     */
    private function narrate(Celebration $celebration, User $recipient, array $needsWork, array $mastery): array
    {
        try {
            $result = $this->gateway->complete($recipient, AiPurpose::Coach, 'gap_guidance', 1, [
                'role_title' => $celebration->role_title,
                'needs_work' => $needsWork === [] ? 'none flagged yet' : implode(', ', $needsWork),
                'mastery_lines' => collect($mastery)
                    ->map(fn (array $m) => "{$m['name']}: {$m['pct']}% ({$m['band']})")
                    ->implode("\n") ?: 'no module data yet',
            ], ['max_tokens' => 400]);

            $lines = collect(explode("\n", trim($result->text)))->map(fn ($l) => trim($l))->filter();
            $actions = $lines
                ->filter(fn (string $l) => str_starts_with($l, '- ') || preg_match('/^\d+[.)]\s/', $l))
                ->map(fn (string $l) => trim(preg_replace('/^(-|\d+[.)])\s*/', '', $l) ?? $l))
                ->take(3)
                ->values();
            $intro = (string) $lines->first(fn (string $l) => ! str_starts_with($l, '-') && ! preg_match('/^\d+[.)]\s/', $l), '');

            if ($intro !== '' && $actions->count() === 3) {
                return [$intro, $actions->all(), 'ai'];
            }
        } catch (Throwable) {
            // Budget exceeded or transport failure — fall through to fallback.
        }

        return [$this->fallbackIntro($celebration), $this->fallbackActions($needsWork), 'fallback'];
    }

    private function fallbackIntro(Celebration $celebration): string
    {
        return sprintf(
            'Someone on your course just landed a %s offer — the gap between you and that offer is specific, not mysterious.',
            $celebration->role_title,
        );
    }

    /**
     * @param  list<string>  $needsWork
     * @return list<string>
     */
    private function fallbackActions(array $needsWork): array
    {
        $actions = collect($needsWork)->take(3)
            ->map(fn (string $module) => "Rewatch the {$module} recording and redo its lab this week — it's your weakest module.")
            ->values()->all();

        $generic = [
            'Attend every live class this week and stay past the Q&A — attendance is the strongest predictor on our data.',
            'Pass one quiz at 100% this week to prove a module and earn Module Ace.',
            'Solve one coding lab end-to-end without hints and submit it.',
        ];

        while (count($actions) < 3) {
            $actions[] = $generic[count($actions)];
        }

        return $actions;
    }
}
