<?php

declare(strict_types=1);

namespace App\Support\Verification;

use App\Enums\VerificationKind;
use App\Enums\VerificationStatus;
use App\Models\CandidateVerificationCheck;
use App\Models\User;
use App\Support\Audit\AuditLogger;
use Illuminate\Contracts\Container\Container;
use Illuminate\Support\Carbon;
use InvalidArgumentException;

/**
 * The single entry point for candidate verification (PRD-E F9, ADR 0052).
 *
 * Consumers depend on this class only — never on a provider — so the
 * DigiLocker/EPFO/BGV decision stays a config change. The gateway owns the
 * state machine and the audit trail; providers only report outcomes.
 */
final class VerificationGateway
{
    public function __construct(
        private readonly Container $container,
        private readonly AuditLogger $audit,
    ) {}

    /**
     * Submit a check. Re-submitting a failed or expired check is allowed and
     * overwrites it; re-submitting one already verified and live is a no-op,
     * so an impatient candidate cannot churn a vendor bill.
     *
     * @param  array<string, mixed>  $submission
     */
    public function submit(User $candidate, VerificationKind $kind, array $submission = []): CandidateVerificationCheck
    {
        $check = $this->checkFor($candidate, $kind);

        if ($check->effectiveStatus() === VerificationStatus::Verified) {
            return $check;
        }

        $provider = $this->providerFor($kind);
        $outcome = $provider->start($candidate, $kind, $submission);

        $check->fill([
            'status' => $outcome->status->value,
            'provider' => $provider->name(),
            'provider_ref' => $outcome->providerRef,
            'evidence' => $outcome->evidence ?: null,
            'failure_reason' => $outcome->failureReason,
            'submitted_at' => now(),
            'verified_at' => $outcome->status === VerificationStatus::Verified ? now() : null,
            'expires_at' => $outcome->expiresAt ?? $this->expiryFor($kind, $outcome->status),
        ])->save();

        return $check;
    }

    /**
     * Settle a pending check. Used by the ops reviewer and, later, by a
     * provider callback. Audited because a verified badge is a claim we make
     * to employers on a candidate's behalf.
     *
     * @param  array<string, mixed>  $evidence
     */
    public function settle(
        CandidateVerificationCheck $check,
        VerificationStatus $status,
        ?User $actor = null,
        array $evidence = [],
        ?string $failureReason = null,
    ): CandidateVerificationCheck {
        if (! $status->isSettled()) {
            throw new InvalidArgumentException('A check can only be settled as verified or failed.');
        }

        $before = $check->status->value;

        $check->fill([
            'status' => $status->value,
            'evidence' => $evidence ?: $check->evidence,
            'failure_reason' => $failureReason,
            'reviewed_by_id' => $actor?->id,
            'verified_at' => $status === VerificationStatus::Verified ? now() : null,
            'expires_at' => $status === VerificationStatus::Verified
                ? $this->expiryFor($check->kind, $status)
                : null,
        ])->save();

        $this->audit->log(
            'verification.settled',
            $check,
            ['kind' => $check->kind->value, 'from' => $before, 'to' => $status->value],
            $actor,
        );

        return $check;
    }

    /** The candidate's standing row for a kind, created lazily as not-started. */
    public function checkFor(User $candidate, VerificationKind $kind): CandidateVerificationCheck
    {
        return CandidateVerificationCheck::query()->firstOrCreate(
            ['user_id' => $candidate->id, 'kind' => $kind->value],
            ['tenant_id' => $candidate->tenant_id, 'status' => VerificationStatus::NotStarted->value],
        );
    }

    private function providerFor(VerificationKind $kind): VerificationProvider
    {
        $name = (string) config("verification.routing.{$kind->value}", 'manual');
        $class = config("verification.providers.{$name}");

        if (! is_string($class) || $class === '') {
            throw new InvalidArgumentException("No verification provider registered as [{$name}].");
        }

        $provider = $this->container->make($class);

        if (! $provider instanceof VerificationProvider) {
            throw new InvalidArgumentException("[{$class}] is not a verification provider.");
        }

        if (! $provider->supports($kind)) {
            throw new InvalidArgumentException("Provider [{$name}] does not support [{$kind->value}].");
        }

        return $provider;
    }

    private function expiryFor(VerificationKind $kind, VerificationStatus $status): ?Carbon
    {
        if ($status !== VerificationStatus::Verified) {
            return null;
        }

        $days = (int) config("verification.validity_days.{$kind->value}", 365);

        return now()->addDays($days);
    }
}
