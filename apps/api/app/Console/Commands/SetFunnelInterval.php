<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Support\Settings\PlatformSettings;
use Illuminate\Console\Command;

/**
 * Reads or sets how often each course runs its masterclass, so the CRM can
 * change the funnel cadence without a deploy. The value is stored in
 * `platform_settings` and applied over `config('funnel.masterclass_interval_days')`
 * at boot, exactly like the integration keys.
 */
final class SetFunnelInterval extends Command
{
    protected $signature = 'funnel:interval {days? : 7, 14, 20, 25 or 30 — omit to read the current value}';

    protected $description = 'Read or set the masterclass frequency in days';

    public function handle(PlatformSettings $settings): int
    {
        $days = $this->argument('days');

        if ($days !== null) {
            $allowed = ['7', '14', '20', '25', '30'];

            if (! in_array((string) $days, $allowed, true)) {
                $this->error('Choose one of: '.implode(', ', $allowed).' days.');

                return self::FAILURE;
            }

            $settings->save(['funnel' => ['masterclass_interval_days' => (string) $days]]);
            $settings->refresh();
        }

        $this->line((string) json_encode([
            'masterclass_interval_days' => (int) config('funnel.masterclass_interval_days', 7),
        ]));

        return self::SUCCESS;
    }
}
