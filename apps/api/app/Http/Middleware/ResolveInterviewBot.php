<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\EmployerInterview;
use App\Models\InterviewInvite;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * The AI interviewer page a meeting bot opens inside an L1/L2 Zoom call
 * (/interview-bot/{code}) has no login: the private code in its URL is the
 * key. It acts as the candidate for exactly one thing — this round's
 * interview — and only from 30 minutes before the booked slot to 3 hours
 * after it. Any other interview id is "not found".
 */
final class ResolveInterviewBot
{
    private const EARLY_MINUTES = 30;

    private const LATE_HOURS = 3;

    public function handle(Request $request, Closure $next): Response
    {
        $code = (string) $request->route('code');
        abort_unless(preg_match('/^[A-Za-z0-9]{40,64}$/', $code) === 1, 404);

        $invite = InterviewInvite::withoutGlobalScopes()->where('bot_code', $code)->first();
        abort_if($invite === null, 404);

        $at = $invite->scheduled_at;
        abort_if(
            $at === null
            || now()->lt($at->copy()->subMinutes(self::EARLY_MINUTES))
            || now()->gt($at->copy()->addHours(self::LATE_HOURS))
            || ! in_array($invite->status, [InterviewInvite::SCHEDULED, InterviewInvite::OPEN, InterviewInvite::DONE], true),
            403,
            'This interview is not open now.',
        );

        $candidate = User::query()->withoutGlobalScopes()->find($invite->candidate_user_id);
        abort_if($candidate === null, 404);

        $mock = $request->route('mock');
        if ($mock !== null) {
            $interview = $invite->employer_interview_id !== null
                ? EmployerInterview::withoutGlobalScopes()->find($invite->employer_interview_id)
                : null;
            abort_unless($interview !== null && (int) $interview->mock_interview_id === (int) $mock, 404);
        }

        // The controllers behind these routes are the candidate's own interview endpoints.
        $request->route()?->forgetParameter('code');
        $request->attributes->set('interview_invite', $invite);
        $request->setUserResolver(fn () => $candidate);
        Auth::setUser($candidate);

        return $next($request);
    }
}
