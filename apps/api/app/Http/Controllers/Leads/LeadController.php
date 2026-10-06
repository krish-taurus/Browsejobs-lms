<?php

declare(strict_types=1);

namespace App\Http\Controllers\Leads;

use App\Actions\Crm\CaptureLead;
use App\Http\Controllers\Controller;
use App\Http\Requests\Leads\StoreLeadRequest;
use App\Models\Lead;
use App\Models\Scopes\TenantScope;
use App\Models\User;
use App\Support\Crm\PhoneNormalizer;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

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

        $this->alreadyRegistered($request->string('phone')->toString());

        $lead = $capture->handle($tenant, [
            ...$request->safe()->except('consent'),
            'ip' => $request->ip(),
            'consented_at' => now(),
            'consent_version' => 'v1',
        ]);

        return response()->json(['status' => 'received', 'id' => $lead->id], 201);
    }

    /**
     * Stop the same phone booking twice.
     *
     * Two different people hit this: someone who already has an account (tell
     * them to sign in — a second lead helps nobody), and someone who filled the
     * form earlier but has no account yet (they cannot log in, so telling them
     * to would be a dead end — reassure them instead).
     *
     * Raised as a validation error on `phone` so the form shows it inline, the
     * same way it shows every other field error.
     */
    private function alreadyRegistered(string $phone): void
    {
        $digits = PhoneNormalizer::normalize($phone);
        $last10 = mb_substr($digits, -10);

        if (mb_strlen($last10) < 10) {
            return;
        }

        $hasAccount = User::query()->withoutGlobalScope(TenantScope::class)
            ->whereRaw("REPLACE(REPLACE(REPLACE(phone, ' ', ''), '-', ''), '+', '') LIKE ?", ["%{$last10}"])
            ->exists();

        if ($hasAccount) {
            throw ValidationException::withMessages([
                'phone' => 'You already have a BrowseJobs account with this number. Please log in instead.',
            ]);
        }

        $hasLead = Lead::query()->withoutGlobalScope(TenantScope::class)
            ->where('phone_normalized', 'like', "%{$last10}")
            ->exists();

        if ($hasLead) {
            throw ValidationException::withMessages([
                'phone' => 'This number is already booked. Check your WhatsApp for the link — message us if it has not arrived.',
            ]);
        }
    }
}
