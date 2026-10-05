<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\MentorProfile;
use App\Models\Scopes\TenantScope;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

/**
 * Brings the interview panel in config/interviews.php into the database.
 *
 * Idempotent by email: run it after editing the panel and it creates what is
 * missing, updates what changed, and deactivates the profile of anyone who was
 * removed from the list. Existing bookings are never touched — a mentor who
 * leaves the panel keeps their history, they just stop taking new slots.
 */
class SyncInterviewPanel extends Command
{
    protected $signature = 'interviews:sync-panel';

    protected $description = 'Create/refresh the interview panel (Tech Mentors and Tech Managers) from config.';

    public function handle(): int
    {
        /** @var list<array{name: string, email: string, phone: string, round: int, headline: string}> $panel */
        $panel = config('interviews.panel', []);

        if ($panel === []) {
            $this->error('config/interviews.php has an empty panel.');

            return self::FAILURE;
        }

        $tenantId = Tenant::query()->value('id');

        if ($tenantId === null) {
            $this->error('No tenant found.');

            return self::FAILURE;
        }

        $seenProfileIds = [];

        foreach ($panel as $member) {
            $user = User::query()->withoutGlobalScope(TenantScope::class)
                ->where('email', $member['email'])->first();

            if ($user === null) {
                $user = User::query()->create([
                    'tenant_id' => $tenantId,
                    'name' => $member['name'],
                    'email' => $member['email'],
                    'phone' => $member['phone'],
                    'user_type' => 'staff',
                    'is_active' => true,
                    // They sign in to the CRM, not here. A random secret keeps
                    // the account unusable until someone deliberately resets it.
                    'password' => bcrypt(Str::random(40)),
                ]);

                $this->line("Created LMS account for {$member['name']} (#{$user->id}).");
            } elseif (blank($user->phone)) {
                // Only fill a gap — never overwrite a number someone has fixed by hand.
                $user->forceFill(['phone' => $member['phone']])->save();
            }

            $profile = MentorProfile::query()->withoutGlobalScope(TenantScope::class)
                ->firstOrNew(['tenant_id' => $tenantId, 'user_id' => $user->id]);

            $profile->fill([
                'headline' => $member['headline'],
                'interview_round' => $member['round'],
                'is_active' => true,
            ]);

            // expertise_tags is NOT NULL on the table and carries no meaning for
            // an interview panel, so seed it once and leave any hand-edit alone.
            if (blank($profile->expertise_tags)) {
                $profile->expertise_tags = [$member['headline']];
            }

            $profile->save();
            $seenProfileIds[] = $profile->id;

            $this->line("Panel: {$member['name']} — round {$member['round']} ({$member['headline']}).");
        }

        // Anyone dropped from config stops taking new slots but keeps their past.
        $retired = MentorProfile::query()->withoutGlobalScope(TenantScope::class)
            ->whereNotNull('interview_round')
            ->whereNotIn('id', $seenProfileIds)
            ->update(['is_active' => false]);

        if ($retired > 0) {
            $this->warn("Deactivated {$retired} panel member(s) no longer listed in config.");
        }

        $this->info('Interview panel synced: '.count($panel).' member(s).');

        return self::SUCCESS;
    }
}
