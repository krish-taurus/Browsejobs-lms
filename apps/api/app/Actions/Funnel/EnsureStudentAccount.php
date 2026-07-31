<?php

declare(strict_types=1);

namespace App\Actions\Funnel;

use App\Actions\Auth\AssignRoleToUser;
use App\Models\Lead;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;

/**
 * Creates a student account for a masterclass lead who never registered, so
 * the weekend funnel can seat them without waiting (runs within a tenant
 * context). Safe because students authenticate with an OTP to this exact
 * phone/email — no password is involved — and the lead already consented at
 * capture. Mirrors RegisterController's account creation.
 */
final readonly class EnsureStudentAccount
{
    public function __construct(private AssignRoleToUser $assignRole) {}

    public function handle(Lead $lead): ?User
    {
        if (blank($lead->phone)) {
            return null; // OTP login needs a reachable contact
        }

        $user = User::query()->create([
            'tenant_id' => $lead->tenant_id,
            'name' => $lead->name ?: $lead->phone,
            'phone' => $lead->phone,
            'email' => $lead->email ?: null,
            'user_type' => 'student',
            'telemetry_consent_at' => now(),
            'consent_version' => (string) config('dpdp.consent_version', 'v1'),
        ]);

        try {
            $this->assignRole->handle($user, 'student');
        } catch (ModelNotFoundException) {
            // Role table not seeded in this environment — the membership still
            // works; the role can be granted later.
        }

        return $user;
    }
}
