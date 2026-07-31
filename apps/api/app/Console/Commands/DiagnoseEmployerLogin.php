<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\EmployerMember;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

/**
 * Explain why an employer sign-in is being refused.
 *
 * EmployerLogin rejects with one deliberately vague message so the form
 * never tells an attacker which half of a credential pair was wrong. That
 * is right for the browser and useless for an operator, so this reports
 * each condition separately from the console, where the account holder is
 * already trusted.
 *
 * Reports only — it changes nothing. `employer:grant-owner` fixes.
 */
final class DiagnoseEmployerLogin extends Command
{
    protected $signature = 'employer:diagnose {email} {--tenant=}';

    protected $description = 'Report why an employer account cannot sign in.';

    public function handle(): int
    {
        $email = mb_strtolower(trim((string) $this->argument('email')));

        $tenant = $this->option('tenant') !== null
            ? Tenant::query()->find((int) $this->option('tenant'))
            : Tenant::query()->orderBy('id')->first();

        if ($tenant === null) {
            $this->error('No tenant found.');

            return self::FAILURE;
        }

        $this->line("Tenant: [{$tenant->id}] {$tenant->name}");

        $user = User::query()->withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->where('email', $email)
            ->first();

        if ($user === null) {
            $this->error("✗ No user with email [{$email}] on this tenant.");
            $this->line('  Note the account must belong to the tenant the portal resolves by domain.');
            $this->newLine();
            $this->line("Fix: php artisan employer:grant-owner {$email}");

            return self::FAILURE;
        }

        $this->info("✓ User found (id {$user->id}, name: {$user->name}).");

        $ok = true;

        // A row inserted by hand usually stores the password as plain text.
        // Hash::check then fails against every password, including the right one.
        if ($user->password === null || $user->password === '') {
            $this->error('✗ No password set.');
            $ok = false;
        } elseif (! str_starts_with($user->password, '$2y$') && ! str_starts_with($user->password, '$argon')) {
            $this->error('✗ Password is not a hash — it looks like plain text.');
            $this->line('  Sign-in compares against a bcrypt/argon hash, so it can never match.');
            $ok = false;
        } else {
            $this->info('✓ Password is hashed.');
        }

        $hasMembership = EmployerMember::withoutGlobalScopes()->where('user_id', $user->id)->exists();

        if ($user->user_type !== 'employer' && ! $hasMembership) {
            $this->error("✗ user_type is [{$user->user_type}] and there is no workspace membership.");
            $this->line('  The portal needs one or the other.');
            $ok = false;
        } else {
            $this->info("✓ Employer access (user_type: {$user->user_type}, membership: ".($hasMembership ? 'yes' : 'no').').');
        }

        if (! $user->is_active) {
            $this->error('✗ Account is deactivated (is_active = 0).');
            $ok = false;
        } else {
            $this->info('✓ Account is active.');
        }

        if ($user->two_factor_enabled) {
            $this->warn('! Two-factor is on — sign-in will ask for an emailed code.');
        }

        $this->newLine();

        if ($ok) {
            $this->info('Everything the portal checks is in order.');
            $this->line('If it still refuses, the password itself is wrong. Reset it with:');
        } else {
            $this->line('Fix all of the above in one step with:');
        }

        $this->line("  php artisan employer:grant-owner {$email}");

        return $ok ? self::SUCCESS : self::FAILURE;
    }
}
