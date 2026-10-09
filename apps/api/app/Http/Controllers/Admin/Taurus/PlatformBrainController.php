<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Taurus;

use App\Actions\Taurus\SaveBrainSettings;
use App\Actions\Taurus\TestBrainConnection;
use App\Http\Controllers\Controller;
use App\Http\Requests\Taurus\TestBrainRequest;
use App\Http\Requests\Taurus\UpdateBrainRequest;
use App\Support\Settings\PlatformSettings;
use App\Support\Taurus\BrainStatus;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The PLATFORM brain (ADR 0052): the BrowseJobs recruitment brain behind the
 * employer hiring floor, and Taurus HQ's fallback. Stored in the owner-only
 * `taurus` platform_settings group plus the shared `ai` provider keys.
 * Owner-only (`taurus.owner`); no response ever contains a secret.
 */
final class PlatformBrainController extends Controller
{
    public function show(BrainStatus $brain): JsonResponse
    {
        return response()->json(['data' => $brain->toArray()]);
    }

    public function update(UpdateBrainRequest $request, SaveBrainSettings $save, BrainStatus $brain): JsonResponse
    {
        $save->handle($request->validated(), $request->user());

        return response()->json(['data' => $brain->toArray()]);
    }

    /** Forget a stored platform key: an LLM provider's, or the voice's (`elevenlabs`). */
    public function clearProvider(Request $request, string $provider, PlatformSettings $settings, BrainStatus $brain): JsonResponse
    {
        if ($provider === 'elevenlabs') {
            $settings->clear(['taurus' => ['elevenlabs_api_key']], $request->user());
        } elseif (in_array($provider, BrainStatus::PROVIDERS, true)) {
            $settings->clear(['ai' => ["{$provider}_api_key"]], $request->user());
        } else {
            abort(404);
        }

        return response()->json(['data' => $brain->toArray()]);
    }

    public function test(TestBrainRequest $request, TestBrainConnection $test): JsonResponse
    {
        return response()->json(['data' => $test->handle((string) $request->validated('target'))]);
    }
}
