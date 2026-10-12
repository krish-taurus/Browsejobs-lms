<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\Employers\OpenInterviewRound;
use App\Actions\Employers\StartRoundInterview;
use App\Models\EmployerJob;
use App\Models\InterviewInvite;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * The meeting bot's interviewer page has joined the candidate's Zoom call and
 * the candidate is there: start (or resume) this round's interview. The rest
 * of the conversation — questions spoken, answers transcribed, scoring — goes
 * through the candidate's own interview endpoints, under ResolveInterviewBot.
 */
final class InterviewBotController extends Controller
{
    public function start(Request $request, OpenInterviewRound $open, StartRoundInterview $start): JsonResponse
    {
        /** @var InterviewInvite $invite */
        $invite = $request->attributes->get('interview_invite');

        // The round normally opens that morning; if that has not happened yet, open it now.
        if ($invite->employer_interview_id === null && $invite->status === InterviewInvite::SCHEDULED) {
            $open->handle($invite);
            $invite->refresh();
        }
        if ($invite->employer_interview_id === null) {
            throw ValidationException::withMessages(['invite' => $invite->error ?? 'This interview could not be opened.']);
        }

        $mock = $start->handle($invite, $request->user());
        $invite->update(['bot_status' => 'in_call']);
        $job = EmployerJob::withoutGlobalScopes()->with('workspace:id,name')->find($invite->employer_job_id);

        return response()->json(['data' => [
            'mock_id' => $mock->id,
            'first_name' => $invite->firstName(),
            'role' => $job?->title,
            'company' => $job?->workspace?->name,
            'round' => $invite->roundLabel(),
        ]]);
    }
}
