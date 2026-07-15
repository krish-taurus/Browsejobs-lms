<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Tenant;
use Illuminate\Database\Seeder;

/**
 * Seeds tenant 1 = BrowseJobs, the default tenant and reference theme.
 *
 * The branding block is the single source of the design system (CLAUDE.md):
 * these values are surfaced to the frontend as CSS variables so whitelabel
 * theming works by swapping this JSON per tenant.
 */
class TenantSeeder extends Seeder
{
    public function run(): void
    {
        Tenant::query()->updateOrCreate(
            ['slug' => 'browsejobs'],
            [
                'name' => 'BrowseJobs',
                'domain' => null,
                'plan' => 'enterprise',
                'status' => 'active',
                'batch_numbering_pattern' => '{COURSE}-{YYYYMM}-{seq}',
                'branding' => [
                    'colors' => [
                        'ink_navy' => '#0A1220',
                        'deep_navy' => '#0E3FA9',
                        'trust_blue' => '#1B6DF0',
                        'sky' => '#E7F1FE',
                        'verify_green' => '#0BA860',
                        'warn_red' => '#D64545',
                        'amber' => '#F5A623',
                        'paper' => '#F6F9FE',
                        'line' => '#DCE6F5',
                        'muted' => '#5A6B85',
                    ],
                    'fonts' => [
                        'display' => 'Sora',
                        'body' => 'Inter',
                        'mono' => 'IBM Plex Mono',
                    ],
                ],
                'feature_flags' => [
                    'crm' => true,
                    'ai_coach' => true,
                    'mock_interviewer' => true,
                    'placement' => true,
                    'leaderboards' => true,
                ],
            ],
        );
    }
}
