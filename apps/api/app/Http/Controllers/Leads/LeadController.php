<?php

declare(strict_types=1);

namespace App\Http\Controllers\Leads;

use App\Actions\Crm\CaptureLead;
use App\Http\Controllers\Controller;
use App\Http\Requests\Leads\StoreLeadRequest;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;

/**
 * Public lead capture (Platform Spec §6.1). Tenant resolved from host. Routes
 * through the CRM CaptureLead action so every site lead lands on the pipeline
 * with a stage, counselor, score, and speed-to-lead SLA (PRD §6.12).
 */
final class LeadController extends Controller
{
    public function store(StoreLeadRequest $request, CaptureLead $capture): JsonResponse
    {
        $tenant = app(TenantContext::class)->get();

        $lead = $capture->handle($tenant, [
            ...$request->safe()->except('consent'),
            'ip' => $request->ip(),
            'consented_at' => now(),
            'consent_version' => 'v1',
        ]);

        return response()->json(['status' => 'received', 'id' => $lead->id], 201);
    }
}
