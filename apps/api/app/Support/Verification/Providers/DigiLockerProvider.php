<?php

declare(strict_types=1);

namespace App\Support\Verification\Providers;

use App\Enums\VerificationKind;
use App\Models\User;
use App\Support\Verification\VerificationOutcome;
use App\Support\Verification\VerificationProvider;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * DigiLocker-backed identity and education checks (PRD-E F9, ADR 0052).
 *
 * DigiLocker serves documents issued by the body that awarded them, so a
 * match is meaningfully stronger than a scan someone uploaded. That is the
 * whole reason this provider exists, and also the reason it is careful:
 *
 *  - Unconfigured returns *pending*, never verified and never failed. A
 *    missing credential is our problem, and recording it as a failed check
 *    would put a black mark on a candidate for an outage on our side.
 *  - A transport error does the same. Only DigiLocker actually answering
 *    "no match" produces a failure.
 *  - The evidence kept is the fact of the match and its provenance. The
 *    document itself is never stored here — the employer view returns
 *    outcomes only, and the safest way to honour that is to never hold it.
 *
 * Credentials come from Admin → Settings → Background verification, applied
 * over config at boot, so they are never read from code or committed.
 */
final class DigiLockerProvider implements VerificationProvider
{
    private const TIMEOUT_SECONDS = 20;

    public function name(): string
    {
        return 'digilocker';
    }

    public function supports(VerificationKind $kind): bool
    {
        return in_array($kind, [VerificationKind::Identity, VerificationKind::Education], true);
    }

    /**
     * @param  array<string, mixed>  $submission
     */
    public function start(User $candidate, VerificationKind $kind, array $submission): VerificationOutcome
    {
        $base = rtrim((string) config('services.digilocker.base_url', ''), '/');
        $clientId = (string) config('services.digilocker.client_id', '');
        $secret = (string) config('services.digilocker.client_secret', '');

        if ($base === '' || $clientId === '' || $secret === '') {
            // Not wired up yet. The check waits in the ops queue, which is
            // exactly where it would have been on `manual`.
            return VerificationOutcome::pending();
        }

        try {
            $response = Http::timeout(self::TIMEOUT_SECONDS)
                ->withHeaders(['X-Client-Id' => $clientId])
                ->withToken($secret)
                ->asJson()
                ->post("{$base}/v1/verify", [
                    'kind' => $kind->value,
                    'reference' => $submission['reference'] ?? null,
                    'name' => $candidate->name,
                ]);

            if (! $response->successful()) {
                Log::warning('DigiLocker verification call failed', [
                    'kind' => $kind->value,
                    'status' => $response->status(),
                ]);

                return VerificationOutcome::pending();
            }

            /** @var array<string, mixed> $body */
            $body = (array) $response->json();
            $status = is_string($body['status'] ?? null) ? $body['status'] : '';
            $ref = is_string($body['reference'] ?? null) ? $body['reference'] : null;

            return match ($status) {
                'verified' => VerificationOutcome::verified(
                    // Provenance only: which issuer said so, and about what.
                    evidence: array_filter([
                        'issuer' => is_string($body['issuer'] ?? null) ? $body['issuer'] : null,
                        'document_type' => is_string($body['document_type'] ?? null) ? $body['document_type'] : null,
                        'matched_on' => is_string($body['matched_on'] ?? null) ? $body['matched_on'] : null,
                    ]),
                    ref: $ref,
                ),
                'no_match' => VerificationOutcome::failed(
                    is_string($body['reason'] ?? null) && $body['reason'] !== ''
                        ? $body['reason']
                        : 'DigiLocker found no issued document matching these details.',
                    $ref,
                ),
                // Anything else — in review, awaiting consent, rate limited —
                // is genuinely undecided and stays that way.
                default => VerificationOutcome::pending($ref),
            };
        } catch (Throwable $e) {
            Log::warning('DigiLocker verification errored', ['kind' => $kind->value, 'error' => $e->getMessage()]);

            return VerificationOutcome::pending();
        }
    }
}
