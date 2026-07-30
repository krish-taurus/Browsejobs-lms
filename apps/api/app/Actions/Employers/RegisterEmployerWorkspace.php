<?php

declare(strict_types=1);

namespace App\Actions\Employers;

use App\Enums\EmployerRole;
use App\Events\EmployerRegistered;
use App\Models\EmployerMember;
use App\Models\EmployerWorkspace;
use App\Models\User;
use App\Support\Audit\AuditLogger;
use App\Support\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * PRD-E F1 — create a workspace and enrol the creator as Owner.
 */
final readonly class RegisterEmployerWorkspace
{
    public function __construct(private AuditLogger $audit) {}

    /**
     * @param  array{name: string, website?: string|null, industry?: string|null, company_size?: string|null, gstin?: string|null, locations?: array<int, string>|null}  $attributes
     */
    public function handle(User $creator, array $attributes): EmployerWorkspace
    {
        return app(TenantContext::class)->run($creator->tenant, function () use ($creator, $attributes): EmployerWorkspace {
            $workspace = DB::transaction(function () use ($creator, $attributes): EmployerWorkspace {
                $workspace = EmployerWorkspace::create([
                    'name' => $attributes['name'],
                    'slug' => $this->uniqueSlug($attributes['name']),
                    'website' => $attributes['website'] ?? null,
                    'industry' => $attributes['industry'] ?? null,
                    'company_size' => $attributes['company_size'] ?? null,
                    'gstin' => $attributes['gstin'] ?? null,
                    'locations' => $attributes['locations'] ?? null,
                ]);

                EmployerMember::create([
                    'employer_workspace_id' => $workspace->id,
                    'user_id' => $creator->id,
                    'role' => EmployerRole::Owner->value,
                    'joined_at' => now(),
                ]);

                if ($creator->user_type !== 'employer' && $creator->user_type !== 'staff') {
                    $creator->forceFill(['user_type' => 'employer'])->save();
                }

                return $workspace;
            });

            $this->audit->log('employer.workspace_registered', $workspace, [
                'name' => $workspace->name,
            ], $creator);

            EmployerRegistered::dispatch($workspace->fresh());

            return $workspace;
        });
    }

    private function uniqueSlug(string $name): string
    {
        $base = Str::slug($name);
        $slug = $base !== '' ? $base : 'employer';
        $suffix = 1;

        while (EmployerWorkspace::where('slug', $slug)->exists()) {
            $slug = $base.'-'.(++$suffix);
        }

        return $slug;
    }
}
