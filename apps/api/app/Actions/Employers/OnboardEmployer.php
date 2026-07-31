<?php

declare(strict_types=1);

namespace App\Actions\Employers;

use App\Enums\EmployerRole;
use App\Models\EmployerInvite;
use App\Models\EmployerMember;
use App\Models\EmployerWorkspace;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Audit\AuditLogger;
use App\Support\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Onboard an employer from the admin panel (PRD-E F1).
 *
 * The sales-led path. An employer signs a contract with BrowseJobs, and ops
 * needs their workspace to exist and their first user to be able to get in —
 * without anyone SSHing to the server to run a console command.
 *
 * The one rule worth stating: **this never sets the employer's password.**
 * It creates the account and issues a single-use, expiring invite; the
 * employer chooses their own credential. An admin typing someone else's
 * password would mean staff knowingly hold a customer's login, which is
 * worse than the inconvenience it saves — and it would make every later
 * "who did this?" question unanswerable.
 */
final readonly class OnboardEmployer
{
    public function __construct(private AuditLogger $audit) {}

    /**
     * @param  array{company: string, owner_email: string, owner_name?: string|null, industry?: string|null, company_size?: string|null, website?: string|null}  $attributes
     * @return array{workspace: EmployerWorkspace, owner: User, invite: EmployerInvite}
     */
    public function handle(Tenant $tenant, array $attributes, User $actor): array
    {
        $email = Str::lower(trim($attributes['owner_email']));

        return app(TenantContext::class)->run($tenant, function () use ($tenant, $attributes, $email, $actor): array {
            return DB::transaction(function () use ($tenant, $attributes, $email, $actor): array {
                $owner = User::query()->where('email', $email)->first();
                $isNewAccount = $owner === null;

                if ($owner === null) {
                    $owner = User::query()->create([
                        'tenant_id' => $tenant->id,
                        'name' => trim((string) ($attributes['owner_name'] ?? '')) ?: 'Employer',
                        'email' => $email,
                        'user_type' => 'employer',
                        // No usable password. The invite is the only way in,
                        // and it is the employer who sets the credential.
                        'password' => Str::random(64),
                        'is_active' => true,
                    ]);
                } elseif ($owner->user_type === 'student') {
                    // A candidate who now also hires. Their existing account
                    // is reused rather than forked, so one person is one login.
                    $owner->forceFill(['user_type' => 'employer'])->save();
                }

                $existing = EmployerMember::query()
                    ->where('user_id', $owner->id)
                    ->whereHas('workspace', fn ($q) => $q->where('name', $attributes['company']))
                    ->exists();

                if ($existing) {
                    throw ValidationException::withMessages([
                        'owner_email' => 'This person already owns a workspace with that company name.',
                    ]);
                }

                $workspace = EmployerWorkspace::query()->create([
                    'tenant_id' => $tenant->id,
                    'name' => $attributes['company'],
                    'slug' => $this->uniqueSlug($attributes['company']),
                    'website' => $attributes['website'] ?? null,
                    'industry' => $attributes['industry'] ?? null,
                    'company_size' => $attributes['company_size'] ?? null,
                ]);

                EmployerMember::query()->create([
                    'tenant_id' => $tenant->id,
                    'employer_workspace_id' => $workspace->id,
                    'user_id' => $owner->id,
                    'role' => EmployerRole::Owner->value,
                    'joined_at' => now(),
                ]);

                $invite = EmployerInvite::query()->create([
                    'tenant_id' => $tenant->id,
                    'employer_workspace_id' => $workspace->id,
                    'invited_by_id' => $actor->id,
                    'email' => $email,
                    'role' => EmployerRole::Owner->value,
                    // Only a freshly created account may have its first
                    // password set from the link. Reusing an existing
                    // account's email must never let a token overwrite the
                    // credential that account already has.
                    'grants_credential' => $isNewAccount,
                    'token' => Str::random(64),
                    'expires_at' => now()->addDays((int) config('employers.invite_ttl_days')),
                ]);

                $this->audit->log('employer.onboarded', $workspace, [
                    'company' => $workspace->name,
                    'owner_email' => $email,
                    // The token is deliberately absent: an audit row is read by
                    // more people than should be able to use the invite.
                    'invite_expires_at' => $invite->expires_at?->toIso8601String(),
                ], $actor);

                return ['workspace' => $workspace, 'owner' => $owner, 'invite' => $invite];
            });
        });
    }

    private function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'employer';
        $slug = $base;
        $n = 2;

        while (EmployerWorkspace::query()->where('slug', $slug)->exists()) {
            $slug = "{$base}-{$n}";
            $n++;
        }

        return $slug;
    }
}
