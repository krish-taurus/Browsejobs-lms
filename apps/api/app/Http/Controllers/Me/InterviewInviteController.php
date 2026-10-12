<?php

declare(strict_types=1);

namespace App\Http\Controllers\Me;

use App\Actions\Employers\SendInterviewInvites;
use App\Actions\Employers\StartRoundInterview;
use App\Http\Controllers\Controller;
use App\Models\InterviewInvite;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The candidate's interview link (/hire-interview/{token}).
 *
 * show() is public — the page needs to say what the interview is before the
 * candidate logs in — so it reveals only the first name, role, company, round
 * and day; never a phone number or score. start() needs the candidate's own
 * login (OTP to the WhatsApp number) and opens the voice interview room.
 */
final class InterviewInviteController extends Controller
{
    public function show(string $token): JsonResponse
    {
        $invite = $this->find($token);
        $job = $invite->job()->withoutGlobalScopes()->with('workspace:id,name')->first();

        return response()->json(['data' => [
            'first_name' => $invite->firstName(),
            'role' => $job?->title,
            'company' => $job?->workspace?->name,
            'round' => strtoupper($invite->round),
            'status' => $invite->status,
            'day' => $invite->chosen_date !== null ? SendInterviewInvites::label($invite->chosen_date->toDateString()) : null,
            'open' => $invite->status === InterviewInvite::OPEN,
        ]]);
    }

    public function start(Request $request, string $token, StartRoundInterview $start): JsonResponse
    {
        $invite = $this->find($token);
        $mock = $start->handle($invite, $request->user());

        return response()->json(['data' => [
            'mock_id' => $mock->id,
            'room_url' => "/job-interview/{$mock->id}/room",
        ]]);
    }

    private function find(string $token): InterviewInvite
    {
        abort_unless(preg_match('/^[A-Za-z0-9]{20,64}$/', $token) === 1, 404);

        return InterviewInvite::withoutGlobalScopes()->with('candidate:id,name')->where('token', $token)->firstOrFail();
    }
}
