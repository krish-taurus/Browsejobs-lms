<?php

declare(strict_types=1);

namespace App\Support\Dpdp;

use App\Models\CvProfile;
use App\Models\User;

/**
 * Fulfils a DPDP erasure request (PRD §8) by anonymising the student's PII while
 * retaining transactional records the business is legally required to keep
 * (payments, invoices, audit trail). Name/email/phone are scrubbed and login
 * credentials cleared, so the person is no longer identifiable but financial and
 * compliance records stay intact. Idempotent.
 */
final class AnonymizeStudent
{
    public function handle(User $student): void
    {
        // Free-text personal facts the student authored.
        CvProfile::query()->where('user_id', $student->id)->update(['data' => CvProfile::EMPTY]);

        $student->forceFill([
            'name' => 'Deleted user '.$student->id,
            'email' => null,
            'phone' => null,
            'password' => null,          // no credential login possible after erasure
            'two_factor_enabled' => false,
            'anonymized_at' => now(),
        ])->save();
    }
}
