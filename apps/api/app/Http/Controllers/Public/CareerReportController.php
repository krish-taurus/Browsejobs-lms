<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public;

use App\Actions\Crm\CaptureLead;
use App\Http\Controllers\Controller;
use App\Support\Cv\CareerReportBuilder;
use App\Support\Cv\QuickAtsCheck;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Free Career Direction Report — SERVER-GATED: the analysis only runs with a
 * name and phone, which register the caller as a lead through the standard
 * lead pipeline (consent required). The resume text itself is scored and
 * discarded, never stored.
 */
final class CareerReportController extends Controller
{
    public function __invoke(Request $request, CareerReportBuilder $builder, QuickAtsCheck $ats, CaptureLead $capture): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'phone' => ['required', 'string', 'max:20'],
            'email' => ['nullable', 'email', 'max:190'],
            'consent' => ['accepted'],
            'text' => ['required', 'string', 'min:80', 'max:20000'],
        ]);

        // Registering IS the gate: the lead lands on the CRM pipeline exactly
        // like the lead modal's, before any analysis is returned.
        $capture->handle(app(TenantContext::class)->get(), [
            'lead_type' => 'counselling',
            'name' => $validated['name'],
            'phone' => $validated['phone'],
            'email' => $validated['email'] ?? null,
            'page' => '/career-report',
            'ip' => $request->ip(),
            'consented_at' => now(),
            'consent_version' => 'v1',
        ]);

        return response()->json(['data' => $builder->build($validated['text'], $ats)]);
    }
}
