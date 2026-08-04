<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Support\Settings\PlatformSettings;
use Illuminate\Console\Command;

/**
 * Reads or sets the two class-access rules the CRM owns: how early the Join
 * button opens, and how long a student may keep attending after a fee falls due
 * before live classes lock. Stored in `platform_settings` and applied over
 * config at boot, so a change takes effect without a deploy.
 */
final class SetClassAccessSettings extends Command
{
    protected $signature = 'classes:access
        {--join-minutes= : 1, 5, 10, 15, 30 or 60}
        {--grace-days= : 0, 3, 5, 7, 10, 14, 21 or 30}
        {--hard-block-days= : 3, 5, 7, 10, 14, 21 or 30}';

    protected $description = 'Read or set the join window and the fee grace period';

    /** @var array<string, array{setting: string, options: list<string>}> */
    private const FIELDS = [
        'join-minutes' => ['setting' => 'join_opens_minutes_before', 'options' => ['1', '5', '10', '15', '30', '60']],
        'grace-days' => ['setting' => 'fee_grace_days', 'options' => ['0', '3', '5', '7', '10', '14', '21', '30']],
        'hard-block-days' => ['setting' => 'fee_hard_block_after_days', 'options' => ['3', '5', '7', '10', '14', '21', '30']],
    ];

    public function handle(PlatformSettings $settings): int
    {
        $input = [];

        foreach (self::FIELDS as $option => $field) {
            $value = $this->option($option);

            if ($value === null) {
                continue;
            }

            if (! in_array((string) $value, $field['options'], true)) {
                $this->error("--{$option} must be one of: ".implode(', ', $field['options']));

                return self::FAILURE;
            }

            $input[$field['setting']] = (string) $value;
        }

        if ($input !== []) {
            $settings->save(['classes' => $input]);
            $settings->refresh();
        }

        $this->line((string) json_encode([
            'join_opens_minutes_before' => (int) config('classes.join_opens_minutes_before', 1),
            'fee_grace_days' => (int) config('fees.ladder.grace_days', 5),
            'fee_hard_block_after_days' => (int) config('fees.ladder.hard_block_after_days', 7),
        ]));

        return self::SUCCESS;
    }
}
