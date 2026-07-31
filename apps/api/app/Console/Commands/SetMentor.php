<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Console\Commands\Concerns\ResolvesCrmTargets;
use App\Models\Course;
use App\Models\MentorProfile;
use App\Models\Scopes\TenantScope;
use App\Models\User;
use Illuminate\Console\Command;

/**
 * Adds a staff user to the mentor pool (or updates their profile) — the CRM's
 * counterpart of the LMS admin Mentors page. Availability stays with the
 * mentor (their own hub): admins manage WHO mentors, mentors manage WHEN.
 */
final class SetMentor extends Command
{
    use ResolvesCrmTargets;

    protected $signature = 'mentor:set
        {user : Staff user id or email}
        {--name= : Display name — creates the staff account if the email is new}
        {--phone= : Phone for a newly created staff account}
        {--headline= : Public headline (e.g. "Senior Data Engineer @ X")}
        {--bio= : Short bio}
        {--expertise= : Comma-separated expertise tags (e.g. "SQL,Spark,Interviews")}
        {--courses= : Comma-separated course slugs the mentor covers (empty = generalist)}
        {--active= : 1 to activate, 0 to deactivate}';

    protected $description = 'Add a staff user to the mentor pool or update their mentor profile';

    public function handle(): int
    {
        $identifier = (string) $this->argument('user');

        $user = User::query()->withoutGlobalScope(TenantScope::class)
            ->where('user_type', 'staff')
            ->when(
                ctype_digit($identifier),
                fn ($q) => $q->whereKey((int) $identifier),
                fn ($q) => $q->where('email', $identifier),
            )
            ->first();

        // Mentors managed from the CRM may not exist in the LMS yet — create
        // the staff account on the fly when a name is supplied (CRM Tech
        // Mentors flow). Id lookups never auto-create.
        if ($user === null && ! ctype_digit($identifier) && $this->option('name')) {
            $user = User::query()->create([
                'tenant_id' => \App\Models\Tenant::query()->value('id'),
                'name' => (string) $this->option('name'),
                'email' => $identifier,
                'phone' => $this->option('phone') ?: null,
                'user_type' => 'staff',
            ]);
            $this->info("Created staff account #{$user->id} for {$user->email}.");
        }

        if ($user === null) {
            $this->error("Staff user '{$identifier}' not found — pass --name to create the account.");

            return self::FAILURE;
        }

        return $this->runForTenant($user->tenant_id, function () use ($user): int {
            $expertise = array_values(array_filter(array_map('trim', explode(',', (string) $this->option('expertise')))));

            $courseIds = null;
            if ($this->option('courses') !== null) {
                $slugs = array_values(array_filter(array_map('trim', explode(',', (string) $this->option('courses')))));
                $courseIds = $slugs === [] ? null : Course::query()->whereIn('slug', $slugs)->pluck('id')->map(fn ($id) => (int) $id)->all();
            }

            $profile = MentorProfile::query()->firstOrNew(['user_id' => $user->id]);
            $profile->tenant_id = $user->tenant_id;

            if ($this->option('headline') !== null) {
                $profile->headline = $this->option('headline') ?: null;
            }
            if ($this->option('bio') !== null) {
                $profile->bio = $this->option('bio') ?: null;
            }
            if ($expertise !== []) {
                $profile->expertise_tags = $expertise;
            } elseif (! $profile->exists) {
                $profile->expertise_tags = ['Mentoring'];
            }
            if ($courseIds !== null || $this->option('courses') === '') {
                $profile->course_ids = $courseIds ?: null;
            }
            if (in_array($this->option('active'), ['0', '1'], true)) {
                $profile->is_active = $this->option('active') === '1';
            } elseif (! $profile->exists) {
                $profile->is_active = true;
            }

            $profile->save();

            $this->info(($profile->wasRecentlyCreated ? 'Added ' : 'Updated ')."{$user->name} in the mentor pool (profile #{$profile->id}, ".($profile->is_active ? 'active' : 'inactive').').');

            return self::SUCCESS;
        });
    }
}
