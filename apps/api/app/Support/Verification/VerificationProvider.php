<?php

declare(strict_types=1);

namespace App\Support\Verification;

use App\Enums\VerificationKind;
use App\Models\User;

/**
 * Transport for one verification check (PRD-E F9, ADR 0052).
 *
 * Deliberately narrow: a provider receives what the candidate submitted and
 * answers with an outcome. It never writes to the database and never decides
 * policy — the gateway owns both, so swapping DigiLocker for another issuer
 * aggregator, or EPFO for a BGV vendor, is one class with no ripple.
 *
 * Implementations must be safe to call from a queued job and must not throw
 * on an ordinary "could not verify" — that is a `VerificationOutcome`, not an
 * exception. Exceptions are reserved for transport failures worth retrying.
 */
interface VerificationProvider
{
    /** Stable slug recorded on the check row, e.g. `manual`, `digilocker`. */
    public function name(): string;

    /** Whether this provider can service the given kind at all. */
    public function supports(VerificationKind $kind): bool;

    /**
     * Begin a check. A provider that resolves instantly returns a settled
     * outcome; one that needs a human or a callback returns a pending
     * outcome carrying its own reference.
     *
     * @param  array<string, mixed>  $submission  what the candidate provided
     */
    public function start(User $candidate, VerificationKind $kind, array $submission): VerificationOutcome;
}
