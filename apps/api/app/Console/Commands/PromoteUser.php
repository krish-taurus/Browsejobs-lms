<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Role;
use App\Models\User;
use Illuminate\Console\Command;

/**
 * Grant a role (default super-admin) to a user by email — the supported way to
 * give the platform owner access to platform-wide screens like Settings, which
 * only super-admin can reach. Runs outside tenant scope so the owner account is
 * found regardless of tenant.
 *
 * Usage: php artisan user:promote owner@example.com
 *        php artisan user:promote coach@example.com --role=trainer
 */
final class PromoteUser extends Command
{
    protected $signature = 'user:promote {email : The user\'s email} {--role=super-admin : Role slug to grant}';

    protected $description = 'Grant a role (default super-admin) to a user by email.';

    public function handle(): int
    {
        $email = trim((string) $this->argument('email'));
        $roleSlug = (string) $this->option('role');

        $user = User::query()->withoutGlobalScopes()->where('email', $email)->first();
        if ($user === null) {
            $this->error("No user found with email [{$email}].");

            return self::FAILURE;
        }

        $role = Role::query()->where('slug', $roleSlug)->first();
        if ($role === null) {
            $available = Role::query()->orderBy('slug')->pluck('slug')->implode(', ');
            $this->error("No role [{$roleSlug}]. Available: {$available}");

            return self::FAILURE;
        }

        if ($user->hasRole($roleSlug)) {
            $this->info("{$user->name} ({$email}) already has the {$roleSlug} role.");

            return self::SUCCESS;
        }

        $user->assignRole($role);
        $this->info("Granted {$roleSlug} to {$user->name} ({$email}).");

        return self::SUCCESS;
    }
}
