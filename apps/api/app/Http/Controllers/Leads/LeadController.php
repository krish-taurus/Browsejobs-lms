<?php

declare(strict_types=1);

namespace App\Http\Controllers\Leads;

use App\Http\Controllers\Controller;
use App\Http\Requests\Leads\StoreLeadRequest;
use App\Models\Lead;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;

/**
 * Public lead capture (Platform Spec §6.1). Tenant resolved from host.
 * CRM push + WhatsApp confirmation attach here as queued listeners in P2.1/P2.4.
 */
final class LeadController extends Controller
{
    public function store(StoreLeadRequest $request): JsonResponse
    {
        $tenant = app(TenantContext::class)->get();

        $lead = Lead::query()->create([
            ...$request->safe()->except('consent'),
            'tenant_id' => $tenant->id,
            'ip' => $request->ip(),
            'consented_at' => now(),
            'consent_version' => 'v1',
        ]);

        return response()->json(['status' => 'received', 'id' => $lead->id], 201);
    }
}
