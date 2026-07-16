<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\Telemetry\RecordActivity;
use App\Enums\ActivityType;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Client-reported telemetry ingest (PRD §6.4): watch time + lesson views from the
 * student browser. Only allowlisted event types are accepted; server-derived
 * events (completions, logins, code) come from listeners, not here.
 */
final class ActivityController extends Controller
{
    public function store(Request $request, RecordActivity $record): JsonResponse
    {
        $allowed = array_map(fn (ActivityType $t) => $t->value, ActivityType::clientReportable());

        $validated = $request->validate([
            'type' => ['required', Rule::in($allowed)],
            'value' => ['nullable', 'integer', 'min:0', 'max:86400'],
            'meta' => ['nullable', 'array'],
        ]);

        $record->handle(
            $request->user(),
            ActivityType::from($validated['type']),
            null,
            $request->integer('value') ?: null,
            $request->input('meta'),
        );

        return response()->json(['ok' => true], 201);
    }
}
