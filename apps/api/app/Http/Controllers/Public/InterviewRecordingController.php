<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\EmployerInterview;
use App\Models\EmployerJob;
use App\Models\MockInterview;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;

/**
 * The page behind the recording link in an interview report. The signature on
 * the URL (checked by the `signed` middleware) is the only key: a client with
 * the link can watch, nobody else can guess one. Shows the video and nothing
 * about the candidate beyond the role and company.
 */
final class InterviewRecordingController extends Controller
{
    public function show(int $mock): Response
    {
        $interview = MockInterview::query()->withoutGlobalScopes()->with('blueprint')->find($mock);
        abort_if($interview === null, 404);

        $video = null;
        if ($interview->recording_url !== null) {
            $video = str_starts_with($interview->recording_url, 'http')
                ? $interview->recording_url
                : Storage::disk('s3')->temporaryUrl($interview->recording_url, now()->addHour());
        }

        $job = EmployerInterview::withoutGlobalScopes()->where('mock_interview_id', $interview->id)->first()
            ?->application()->withoutGlobalScopes()->first()
            ?->job()->withoutGlobalScopes()->with('workspace:id,name')->first();

        return response()->view('public.interview-recording', [
            'video' => $video,
            'role' => $job instanceof EmployerJob ? $job->title : ($interview->blueprint?->role_title ?? 'Interview'),
            'company' => $job instanceof EmployerJob ? ($job->workspace?->name ?? '') : '',
            'minutes' => $interview->duration_seconds !== null ? max(1, (int) round($interview->duration_seconds / 60)) : null,
        ])->header('Cache-Control', 'private, no-store')->header('X-Robots-Tag', 'noindex, nofollow');
    }
}
