<?php

declare(strict_types=1);

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Tells the public job board to drop its cached copy of one JD the moment
 * its status actually changes, instead of waiting out the page's timed ISR
 * window — a closed JD should stop looking applyable immediately, not
 * whenever the next visitor happens to trigger a background refresh.
 *
 * Best-effort only: the timed revalidate window is still the real
 * guarantee, so a failed ping here is logged and swallowed rather than
 * retried into the publish/close action's own response time.
 */
final class RevalidatePublicJobBoard implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 1;

    public function __construct(public readonly int $jobId) {}

    public function handle(): void
    {
        $secret = (string) config('services.web.revalidate_secret');
        if ($secret === '') {
            return;
        }

        $url = rtrim((string) config('services.web.url'), '/').'/api/revalidate';

        try {
            Http::withHeaders(['X-Internal-Secret' => $secret])
                ->timeout(5)
                ->post($url, ['job_id' => $this->jobId]);
        } catch (Throwable $e) {
            Log::warning('Public job board revalidation ping failed.', [
                'job_id' => $this->jobId,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
