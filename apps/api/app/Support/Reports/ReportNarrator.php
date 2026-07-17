<?php

declare(strict_types=1);

namespace App\Support\Reports;

use App\Enums\AiPurpose;
use App\Enums\ReportType;
use App\Models\AiEvent;
use App\Models\User;
use App\Services\AI\AiGateway;
use Throwable;

/**
 * Turns the P3.2 structured scoring/risk data into report prose via the AI gateway
 * (PRD §6.10), billed to the passed recipient. Always safe: on a budget/transport
 * failure (or empty output) it returns a deterministic templated narrative instead,
 * so a scheduled report never fails.
 */
final readonly class ReportNarrator
{
    private const MAX_CHARS = 2000;

    public function __construct(private AiGateway $gateway) {}

    /**
     * @param  array<string, string>  $vars
     * @return array{narrative: string, ai_event_id: int|null, source: 'ai'|'fallback'}
     */
    public function narrate(User $billTo, ReportType $type, array $vars): array
    {
        try {
            $result = $this->gateway->complete(
                $billTo,
                AiPurpose::Report,
                $type->prompt(),
                1,
                $vars,
                ['max_tokens' => 600],
            );

            $narrative = $this->sanitize($result->text);
            if ($narrative === '') {
                return $this->fallback($type, $vars);
            }

            $aiEventId = AiEvent::query()
                ->where('user_id', $billTo->id)
                ->where('purpose', AiPurpose::Report->value)
                ->latest('id')
                ->value('id');

            return ['narrative' => $narrative, 'ai_event_id' => $aiEventId, 'source' => 'ai'];
        } catch (Throwable) {
            return $this->fallback($type, $vars);
        }
    }

    /**
     * @param  array<string, string>  $vars
     * @return array{narrative: string, ai_event_id: null, source: 'fallback'}
     */
    private function fallback(ReportType $type, array $vars): array
    {
        $narrative = match ($type) {
            ReportType::WeeklyStudent => $this->weeklyFallback($vars),
            ReportType::TrainerBrief => $this->briefFallback($vars),
            ReportType::CounselorDigest => $this->digestFallback($vars),
            ReportType::SupportThemes => $this->themesFallback($vars),
        };

        return ['narrative' => $this->sanitize($narrative), 'ai_event_id' => null, 'source' => 'fallback'];
    }

    /** @param array<string, string> $vars */
    private function weeklyFallback(array $vars): string
    {
        $wins = trim($vars['went_well'] ?? '');
        $work = trim($vars['needs_work'] ?? '');
        $focus = trim($vars['next_action'] ?? '');

        $lines = [];
        $lines[] = $wins !== '' ? "This week you made real progress on {$wins}." : 'This week you kept showing up — that consistency is what compounds.';
        $lines[] = $work !== '' ? "The area to strengthen next is {$work}." : 'Keep building on every module — steady practice is the biggest predictor of placement.';
        $lines[] = $focus !== '' ? "Your one focus to crack the final interview: {$focus}." : 'Your one focus: finish your next topic and keep the streak alive.';

        return implode("\n\n", $lines);
    }

    /** @param array<string, string> $vars */
    private function briefFallback(array $vars): string
    {
        $batch = trim($vars['batch'] ?? 'your batch');
        $topic = trim($vars['topic'] ?? 'today’s session');
        $atRisk = trim($vars['at_risk'] ?? '');

        $brief = "Pre-class brief for {$batch} — {$topic}.";
        $brief .= $atRisk !== '' ? " Watch for: {$atRisk}. Check in with them and reinforce the fundamentals." : ' The batch is on track — keep the energy up and invite questions.';

        return $brief;
    }

    /** @param array<string, string> $vars */
    private function digestFallback(array $vars): string
    {
        $count = trim($vars['high_risk_count'] ?? '0');
        $movers = trim($vars['movers'] ?? '');

        $digest = "Today: {$count} student(s) at high dropout risk.";
        $digest .= $movers !== '' ? " Rising fastest: {$movers}. Prioritise a call to each — the scripts are on your risk dashboard." : ' No sharp movers today — a good day to follow up on open interventions.';

        return $digest;
    }

    /**
     * The themes report without a model: the counts alone, stated plainly. They are
     * already computed, so this loses the interpretation, not the facts.
     *
     * @param  array<string, string>  $vars
     */
    private function themesFallback(array $vars): string
    {
        $total = trim($vars['total'] ?? '0');
        $categories = trim($vars['by_category'] ?? '');
        $duplicates = trim($vars['duplicates'] ?? '0');

        $note = "Support last week: {$total} ticket(s).";
        $note .= $categories !== '' ? " By category: {$categories}." : '';
        $note .= $duplicates !== '0' ? " {$duplicates} repeated an open ticket — worth a look at what the first answer missed." : '';

        return $note;
    }

    private function sanitize(string $text): string
    {
        $clean = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', '', trim($text)) ?? '';

        return mb_substr($clean, 0, self::MAX_CHARS);
    }
}
