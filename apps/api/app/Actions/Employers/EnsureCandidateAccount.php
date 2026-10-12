<?php

declare(strict_types=1);

namespace App\Actions\Employers;

use App\Models\EmployerCandidate;
use App\Models\Scopes\TenantScope;
use App\Models\User;
use App\Support\Crm\PhoneNormalizer;
use Illuminate\Support\Str;

/**
 * A client-database or Naukri candidate needs an account to sit the
 * interview: they log in with their phone (WhatsApp OTP) and the interview,
 * its recording and its score belong to that account.
 *
 * Reuses an existing account with the same phone (they may already be a
 * BrowseJobs student); otherwise creates a light one. The candidate row
 * remembers it (employer_candidates.user_id), which is also how the CRM's
 * lead import knows to leave these people alone.
 */
final readonly class EnsureCandidateAccount
{
    public function handle(EmployerCandidate $candidate): ?User
    {
        if ($candidate->user_id !== null && ($user = User::query()->find($candidate->user_id)) !== null) {
            return $user;
        }

        $last10 = substr(PhoneNormalizer::normalize((string) $candidate->phone), -10);
        if (strlen($last10) !== 10) {
            return null; // nobody to message, nobody to log in
        }

        $user = User::query()
            ->withoutGlobalScope(TenantScope::class)
            ->where('tenant_id', $candidate->tenant_id)
            ->whereRaw("REPLACE(REPLACE(REPLACE(phone, ' ', ''), '-', ''), '+', '') LIKE ?", ['%'.$last10])
            ->orderBy('id')
            ->first();

        if ($user === null) {
            $email = $candidate->email !== null
                && ! User::query()->withoutGlobalScope(TenantScope::class)->where('email', $candidate->email)->exists()
                    ? $candidate->email
                    // Accounts need a unique email; this one can never receive mail.
                    : "candidate-{$candidate->tenant_id}-{$last10}@candidates.browsejobs.invalid";

            $user = User::query()->create([
                'tenant_id' => $candidate->tenant_id,
                'name' => $candidate->name,
                'email' => $email,
                'phone' => '+91'.$last10,
                'user_type' => 'student',
                'is_active' => true,
                'password' => Str::random(40),
            ]);
        }

        $candidate->forceFill(['user_id' => $user->id])->save();

        return $user;
    }
}
