<?php

declare(strict_types=1);

namespace App\Http\Controllers\Me;

use App\Enums\VerificationKind;
use App\Http\Controllers\Controller;
use App\Support\Tenancy\TenantContext;
use App\Support\Verification\VerificationGateway;
use App\Support\Verification\VerificationProfile;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Candidate-facing verification (PRD-E F9): see the checklist, submit a
 * check. Settling a check is an ops action and lives on the admin side —
 * a candidate can never mark themselves verified.
 */
final class VerificationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $summary = app(TenantContext::class)->run(
            $user->tenant,
            fn (): array => (new VerificationProfile($user))->summary(),
        );

        return response()->json(['data' => $summary]);
    }

    public function submit(Request $request, string $kind, VerificationGateway $gateway): JsonResponse
    {
        $verificationKind = VerificationKind::tryFrom($kind);
        abort_if($verificationKind === null, 404);

        $validated = $request->validate([
            'reference' => ['nullable', 'string', 'max:120'],
            'document_path' => ['nullable', 'string', 'max:500'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $user = $request->user();

        $check = app(TenantContext::class)->run(
            $user->tenant,
            fn () => $gateway->submit($user, $verificationKind, $validated),
        );

        return response()->json(['data' => [
            'kind' => $check->kind->value,
            'status' => $check->effectiveStatus()->value,
            'status_label' => $check->effectiveStatus()->label(),
            'submitted_at' => $check->submitted_at?->toIso8601String(),
        ]]);
    }
}
