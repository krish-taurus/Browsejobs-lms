<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Enums\MessageChannel;
use App\Http\Controllers\Public\DailyBriefController;
use App\Models\Lead;
use App\Models\Scopes\TenantScope;
use App\Support\Messaging\Messenger;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;

/**
 * Sends the Daily Market Brief teaser to open leads: the headline as the
 * hook, the /brief link as the destination (register-to-read converts the
 * click into dashboard activity and, via the masterclass CTA, into a
 * booking). Idempotent per day via a cache flag; skips quietly when there
 * is no brief or no headline.
 */
final class SendDailyBrief implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function handle(Messenger $messenger): void
    {
        $flag = 'daily-brief:sent:'.now()->toDateString();
        if (Cache::has($flag)) {
            return;
        }

        Cache::forget('daily-brief:latest');
        $brief = app(DailyBriefController::class)()->getData(true)['data'] ?? null;
        if (! is_array($brief) || ($brief['headline'] ?? null) === null || ($brief['total'] ?? 0) === 0) {
            return;
        }

        $url = rtrim((string) config('app.frontend_url', 'https://browsejobs.ai'), '/').'/brief';

        Lead::query()
            ->withoutGlobalScope(TenantScope::class)
            // Open pipeline only: skip leads whose stage is a terminal outcome.
            ->whereDoesntHave('stage', fn ($q) => $q->where('is_won', true)->orWhere('is_lost', true))
            ->whereNotNull('phone')
            ->orderBy('id')
            ->chunkById(200, function ($leads) use ($messenger, $brief, $url): void {
                foreach ($leads as $lead) {
                    $messenger->sendRaw($lead->tenant_id, MessageChannel::WhatsApp, $lead->phone, 'daily_brief', [
                        'name' => $lead->name ?? 'there',
                        'headline' => (string) $brief['headline'],
                        'link' => $url,
                    ], $lead);
                }
            });

        Cache::put($flag, true, now()->addDay());
    }
}
