<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Certificate;
use Illuminate\Http\JsonResponse;

/**
 * Public certificate verification (PRD §6.5). No auth and no tenant host required —
 * the QR on a printed certificate must verify from anywhere — so the lookup is by the
 * globally-unique `code` with tenant scopes disabled. Returns only minimal, non-sensitive
 * data and reflects revocation live.
 */
final class CertificateVerifyController extends Controller
{
    public function show(string $code): JsonResponse
    {
        $certificate = Certificate::query()
            ->withoutGlobalScopes()
            ->where('code', $code)
            ->with(['student:id,name', 'course:id,name', 'tenant:id,name'])
            ->first();

        if ($certificate === null) {
            return response()->json(['data' => ['valid' => false]]);
        }

        return response()->json(['data' => [
            'valid' => ! $certificate->isRevoked(),
            'revoked' => $certificate->isRevoked(),
            'student_name' => $certificate->student?->name,
            'course' => $certificate->course?->name,
            'issued_on' => $certificate->issued_at?->toDateString(),
            'tenant' => $certificate->tenant?->name,
            'number' => $certificate->number,
        ]]);
    }
}
