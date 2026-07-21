<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateSettingsRequest;
use App\Support\AI\ProviderResolver;
use App\Support\Settings\PlatformSettings;
use Illuminate\Http\JsonResponse;

/**
 * Platform integration settings (PRD §6.14): LLM / WhatsApp / Vapi / Zoom credentials.
 * Gated `can:manage-settings`, which only a super-admin satisfies (the Gate::before
 * bypass) — these are platform-wide keys, not per-institute config.
 *
 * The controller is thin: the whitelist and all masking/apply/audit logic live in the
 * PlatformSettings service, so a secret is never assembled or returned here.
 */
final class SettingsController extends Controller
{
    public function index(PlatformSettings $settings, ProviderResolver $ai): JsonResponse
    {
        return response()->json(['data' => $settings->schema(), 'ai' => $ai->status()]);
    }

    public function update(UpdateSettingsRequest $request, PlatformSettings $settings, ProviderResolver $ai): JsonResponse
    {
        /** @var array<string, array<string, mixed>> $input */
        $input = $request->input('settings', []);

        $settings->save($input, $request->user());

        return response()->json(['data' => $settings->schema(), 'ai' => $ai->status()]);
    }
}
