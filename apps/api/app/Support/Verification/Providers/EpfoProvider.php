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
 * Employment history checked against the EPFO passbook, via whichever BGV
 * vendor is contracted (PRD-E F9, ADR 0052).
 *
 * A PF contribution history is hard to fabricate, which is why employers ask
 * for it and why this check carries weight. That weight is also why the
 * failure modes are conservative:
 *
 *  - Unconfigured or unreachable returns *pending*. Recording "employment
 *    could not be verified" because our vendor was down would be a claim
 *    about a person, made from our own outage.
 *  - Only an explicit mismatch from the vendor fails the check.
 *  - The evidence kept is the shape of the match — how many employers lined
 *    up, and over what span — never the passbook, the UAN, or the salary.
 *    An employer is entitled to know the history stands up; they are not
 *    entitled to the record behind it.
 */
final class EpfoProvider implements VerificationProvider
{
    private const TIMEOUT_SECONDS = 30;

    public function name(): string
    {
        return 'epfo';
    }

    public function supports(VerificationKind $kind): bool
    {
        return $kind === VerificationKind::Employment;
    }

    /**
     * @param  array<string, mixed>  $submission
     */
    public function start(User $candidate, VerificationKind $kind, array $submission): VerificationOutcome
    {
        $base = rtrim((string) config('services.epfo.base_url', ''), '/');
        $key = (string) config('services.epfo.api_key', '');

        if ($base === '' || $key === '') {
            return VerificationOutcome::pending();
        }

        try {
            $response = Http::timeout(self::TIMEOUT_SECONDS)
                ->withToken($key)
                ->asJson()
                ->post("{$base}/v1/employment/verify", [
                    'name' => $candidate->name,
                    'reference' => $submission['reference'] ?? null,
                ]);

            if (! $response->successful()) {
                Log::warning('EPFO verification call failed', ['status' => $response->status()]);

                return VerificationOutcome::pending();
            }

            /** @var array<string, mixed> $body */
            $body = (array) $response->json();
            $status = is_string($body['status'] ?? null) ? $body['status'] : '';
            $ref = is_string($body['reference'] ?? null) ? $body['reference'] : null;

            return match ($status) {
                'verified' => VerificationOutcome::verified(
                    evidence: array_filter([
                        'employers_matched' => isset($body['employers_matched'])
                            ? (int) $body['employers_matched']
                            : null,
                        'months_covered' => isset($body['months_covered'])
                            ? (int) $body['months_covered']
                            : null,
                    ], static fn ($v): bool => $v !== null),
                    ref: $ref,
                ),
                'mismatch' => VerificationOutcome::failed(
                    is_string($body['reason'] ?? null) && $body['reason'] !== ''
                        ? $body['reason']
                        : 'The contribution record does not match the roles or dates claimed.',
                    $ref,
                ),
                default => VerificationOutcome::pending($ref),
            };
        } catch (Throwable $e) {
            Log::warning('EPFO verification errored', ['error' => $e->getMessage()]);

            return VerificationOutcome::pending();
        }
    }
}
