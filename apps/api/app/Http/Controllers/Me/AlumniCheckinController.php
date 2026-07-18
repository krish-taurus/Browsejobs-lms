<?php

declare(strict_types=1);

namespace App\Http\Controllers\Me;

use App\Http\Controllers\Controller;
use App\Models\AlumniCheckin;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Alumni check-in surface for the student portal (PRD §6.21). Lists the
 * alumnus's own pending 6/12-month check-ins and records their response — the
 * long-term outcome + referral signal.
 */
final class AlumniCheckinController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $pending = AlumniCheckin::query()
            ->where('user_id', $request->user()->id)
            ->where('status', AlumniCheckin::STATUS_SCHEDULED)
            ->orderBy('scheduled_for')
            ->get()
            ->map(fn (AlumniCheckin $c) => [
                'id' => $c->id,
                'milestone' => $c->milestone,
                'months' => AlumniCheckin::MILESTONE_MONTHS[$c->milestone] ?? null,
            ]);

        return response()->json(['data' => $pending]);
    }

    public function respond(Request $request, AlumniCheckin $checkin): JsonResponse
    {
        abort_unless($checkin->user_id === $request->user()->id, 404);
        abort_if($checkin->status === AlumniCheckin::STATUS_RESPONDED, 422, 'You have already responded to this check-in.');

        $data = $request->validate([
            'still_employed' => ['required', 'boolean'],
            'current_company' => ['nullable', 'string', 'max:190'],
            'current_lpa' => ['nullable', 'numeric', 'min:0', 'max:1000'],
            'would_refer' => ['required', 'boolean'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $checkin->update([
            'still_employed' => (bool) $data['still_employed'],
            'current_company' => $data['current_company'] ?? null,
            'current_ctc_paise' => isset($data['current_lpa']) ? (int) round(((float) $data['current_lpa']) * 100_000 * 100) : null,
            'would_refer' => (bool) $data['would_refer'],
            'notes' => $data['notes'] ?? null,
            'status' => AlumniCheckin::STATUS_RESPONDED,
            'responded_at' => now(),
        ]);

        return response()->json(['data' => ['status' => 'responded']]);
    }
}
