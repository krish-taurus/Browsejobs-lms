<?php

declare(strict_types=1);

namespace App\Actions\Engagement;

use App\Enums\AiPurpose;
use App\Models\Course;
use App\Models\PulseDigest;
use App\Models\PulseItem;
use App\Models\User;
use App\Services\AI\AiGateway;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Throwable;

/**
 * Builds one tenant's Market Pulse digest for a day (PRD §6.19) from the
 * curated items inside the window. AI-summarised with sources; falls back to
 * a deterministic source-attributed list. Idempotent per (tenant, date).
 */
final readonly class BuildMarketPulse
{
    public function __construct(private AiGateway $gateway) {}

    public function handle(int $tenantId, User $billTo, ?Carbon $date = null): ?PulseDigest
    {
        $date ??= now();

        $items = PulseItem::query()->withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->whereDate('published_on', '>=', $date->copy()->subDays((int) config('engagement.pulse_window_days', 3))->toDateString())
            ->orderByDesc('published_on')
            ->limit(10)
            ->get();

        if ($items->isEmpty()) {
            return null; // Nothing curated — no digest, never invented news.
        }

        [$narrative, $source] = $this->narrate($tenantId, $billTo, $items, $date);

        return PulseDigest::query()->withoutGlobalScopes()->updateOrCreate(
            ['tenant_id' => $tenantId, 'digest_date' => $date->toDateString()],
            ['narrative' => $narrative, 'item_ids' => $items->pluck('id')->all(), 'source' => $source],
        );
    }

    /**
     * @param  Collection<int, PulseItem>  $items
     * @return array{0: string, 1: string}
     */
    private function narrate(int $tenantId, User $billTo, $items, Carbon $date): array
    {
        $courses = Course::query()->withoutGlobalScopes()
            ->where('tenant_id', $tenantId)->where('status', 'live')
            ->pluck('name', 'slug');

        try {
            $result = $this->gateway->complete($billTo, AiPurpose::MarketPulse, 'market_pulse', 1, [
                'date' => $date->toFormattedDateString(),
                'courses' => $courses->values()->implode(', '),
                'items' => $items->map(function (PulseItem $item) use ($courses) {
                    $tie = $item->course_slug !== null && $courses->has($item->course_slug)
                        ? " [ties to: {$courses[$item->course_slug]}]"
                        : '';

                    return "- {$item->title} ({$item->source_name})".
                        ($item->note ? " — {$item->note}" : '').$tie;
                })->implode("\n"),
            ], ['max_tokens' => 400]);

            if (trim($result->text) !== '') {
                return [trim($result->text), 'ai'];
            }
        } catch (Throwable) {
            // Budget/transport failure — deterministic fallback below.
        }

        $fallback = $items
            ->map(fn (PulseItem $item) => "• {$item->title} ({$item->source_name})")
            ->implode("\n");

        return ["Today's market signals, from our curated sources:\n".$fallback, 'fallback'];
    }
}
