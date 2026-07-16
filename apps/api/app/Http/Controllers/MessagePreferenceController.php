<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\Messaging\UpdateMessagePreferenceRequest;
use App\Models\MessagePreference;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Student channel preferences (PRD §6.9): preferred channel + marketing opt-in.
 */
final class MessagePreferenceController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $user = $request->user();
        $pref = MessagePreference::query()->firstOrNew(
            ['user_id' => $user->id],
            [
                'tenant_id' => $user->tenant_id,
                'preferred_channel' => (string) config('messaging.default_channel', 'whatsapp'),
                'marketing_opt_in' => false,
            ],
        );

        return response()->json(['data' => [
            'preferred_channel' => $pref->preferred_channel->value,
            'marketing_opt_in' => $pref->marketing_opt_in,
        ]]);
    }

    public function update(UpdateMessagePreferenceRequest $request): JsonResponse
    {
        $user = $request->user();
        $pref = MessagePreference::query()->updateOrCreate(
            ['user_id' => $user->id],
            [
                'tenant_id' => $user->tenant_id,
                'preferred_channel' => $request->string('preferred_channel')->toString(),
                'marketing_opt_in' => $request->boolean('marketing_opt_in'),
            ],
        );

        return response()->json(['data' => [
            'preferred_channel' => $pref->preferred_channel->value,
            'marketing_opt_in' => $pref->marketing_opt_in,
        ]]);
    }
}
