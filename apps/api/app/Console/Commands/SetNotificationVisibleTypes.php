<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Support\Settings\PlatformSettings;
use Illuminate\Console\Command;

/**
 * Which notification kinds show on the student Alerts page (Sept 2026) —
 * "class,chat" for now, more later, without a deploy. See
 * App\Models\InAppNotification::$fillable's `type` and
 * NotificationController::index(), which reads this same setting.
 */
final class SetNotificationVisibleTypes extends Command
{
    protected $signature = 'notifications:visible-types {--set= : Comma-separated type slugs, e.g. "class,chat". Empty string shows everything.}';

    protected $description = 'Read or set which notification types show on the student Alerts page';

    public function handle(PlatformSettings $settings): int
    {
        $set = $this->option('set');

        if ($set !== null) {
            $types = collect(explode(',', $set))->map(fn ($t) => trim($t))->filter()->implode(',');
            $settings->save(['notifications' => ['visible_types' => $types]]);
            $settings->refresh();
        }

        $current = (string) config('notifications.visible_types', '');
        $this->line('visible_types: '.($current !== '' ? $current : '(empty — every type shows)'));

        return self::SUCCESS;
    }
}
