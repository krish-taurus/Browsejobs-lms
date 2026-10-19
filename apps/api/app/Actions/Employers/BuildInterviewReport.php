<?php

declare(strict_types=1);

namespace App\Actions\Employers;

use App\Models\CvProfile;
use App\Models\EmployerInterview;
use App\Models\EmployerInterviewReport;
use App\Models\EmployerJob;
use App\Models\InterviewInvite;
use App\Models\MockIntegrityEvent;
use App\Models\MockInterview;
use App\Models\User;
use App\Support\Employers\ShortlistSettings;
use App\Support\Mocks\RecordingLink;
use App\Support\Tenancy\TenantContext;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * The employer's report for one graded interview round, written the moment
 * the round is scored — pass or fail — and sent to their WhatsApp with the
 * candidate's CV (SendInterviewReport).
 *
 * One PDF: the score against the pass mark, each skill's score, strengths
 * and concerns, the questions with the candidate's answers, and an integrity
 * section — every moment the room flagged, with the still taken then, and
 * the random check-in stills. "Possible" flags (a second face, eyes off the
 * screen) are camera heuristics with known false positives and are labelled
 * that way; the reviewer judges them from the photo.
 *
 * The CV is rendered from the candidate's CV profile (the original upload is
 * parsed, not kept). Both files are private; WhatsApp gets a short-lived link.
 */
final readonly class BuildInterviewReport
{
    public const DISK = 's3';

    private const MAX_ANSWERS = 12;

    /** Findings that belong in the lip-sync report rather than the general flag list. */
    private const LIP_KINDS = ['lip_voice_mismatch', 'lip_lag', 'lip_no_voice', 'second_voice'];

    /** A person speaking normally has visible lip movement through most of their speech. */
    private const MATCH_OK_PCT = 75;

    private const MATCH_BAD_PCT = 60;

    public function handle(EmployerInterview $interview): ?EmployerInterviewReport
    {
        if ($interview->overall_score === null) {
            return null;
        }

        return app(TenantContext::class)->run($interview->tenant, function () use ($interview): ?EmployerInterviewReport {
            $application = $interview->application()->first();
            $job = $application?->job()->with('workspace:id,name')->first();
            if ($application === null || $job === null) {
                return null;
            }

            /** @var User|null $candidate */
            $candidate = $application->candidate()->withoutGlobalScopes()->first();
            $name = (string) ($candidate?->name ?? 'Candidate');
            $mock = $interview->mock_interview_id !== null ? MockInterview::query()->with('turns')->find($interview->mock_interview_id) : null;
            $events = $mock !== null
                ? MockIntegrityEvent::query()->where('mock_interview_id', $mock->id)->orderBy('occurred_at')->orderBy('id')->get()
                : collect();
            $onZoom = InterviewInvite::query()->where('employer_interview_id', $interview->id)->whereNotNull('zoom_join_url')->exists();

            $passMark = ShortlistSettings::forJob($job)->roundPassMark;
            $score = (int) $interview->overall_score;
            $round = $interview->round_name ?: strtoupper($interview->round);
            $flags = $events->where('severity', MockIntegrityEvent::FLAG)->count();
            $possible = $events->where('severity', MockIntegrityEvent::POSSIBLE)->count();

            $recordingUrl = $mock !== null ? RecordingLink::for($mock) : null;
            $lipReport = $this->lipReport($events, $mock, $onZoom, $recordingUrl);

            $pdf = Pdf::loadView('pdf.interview-report', [
                'company' => (string) ($job->workspace->name ?? ''),
                'role' => $job->title,
                'round' => $round,
                'name' => $name,
                'score' => $score,
                'passMark' => $passMark,
                'passed' => $score >= $passMark,
                'dimensions' => $this->dimensions($interview),
                'summary' => $interview->grading_summary,
                'strengths' => $this->strings($mock?->scorecard['strong_moments'] ?? []),
                'concerns' => $this->strings($mock?->scorecard['weak_moments'] ?? []),
                'answers' => $this->answers($mock),
                'duration' => $mock?->duration_seconds,
                'takenAt' => ($mock->completed_at ?? $interview->submitted_at ?? $interview->graded_at)?->setTimezone('Asia/Kolkata')->format('D j M Y, g:i A'),
                'flagged' => $this->withImages($events->where('severity', '!=', MockIntegrityEvent::INFO)->reject(fn (MockIntegrityEvent $e) => in_array($e->kind, self::LIP_KINDS, true))->values()),
                // Check-in photos only: the lip-sync and voice summaries are measurements, not photos.
                'checkIns' => $this->withImages($events->where('severity', MockIntegrityEvent::INFO)->reject(fn (MockIntegrityEvent $e) => $e->isMeasurement())->values()),
                'lipSync' => $this->lipSync($events, $onZoom),
                'voiceCheck' => $this->voiceCheck($events),
                'flagCounts' => $events->where('severity', '!=', MockIntegrityEvent::INFO)->reject(fn (MockIntegrityEvent $e) => in_array($e->kind, self::LIP_KINDS, true))->groupBy('kind')->map->count(),
                'lipReport' => $lipReport,
                'flags' => $flags,
                'possible' => $possible,
                // Every room that checks integrity also logs check-ins: no events at all means not captured, never "clean".
                'proctored' => $mock !== null && ! $onZoom && $events->isNotEmpty(),
                'onZoom' => $onZoom,
                'hasRecording' => $mock?->recording_url !== null,
                // Skills in the plan nobody was asked about: shown as "not assessed", never as a score.
                'notCovered' => array_values(array_filter((array) ($mock?->scorecard['not_covered'] ?? []), 'is_string')),
                // A link the client can open without logging in (signed, 30 days).
                'recordingUrl' => $recordingUrl,
                'profileUrl' => rtrim((string) config('app.frontend_url'), '/')."/employer/jobs/{$job->id}/candidates/{$application->id}",
                'generatedOn' => now('Asia/Kolkata')->format('j M Y, g:i A'),
            ])->setPaper('a4')->setOption('isFontSubsettingEnabled', true);

            $base = "interview-reports/{$interview->tenant_id}/{$interview->id}";
            Storage::disk(self::DISK)->put("{$base}-report.pdf", $pdf->output(), 'private');
            $cvPath = $candidate !== null ? $this->cv($candidate, $name, $job, "{$base}-cv.pdf") : null;

            $verdict = $score >= $passMark ? 'passed' : "below your pass mark of {$passMark}";
            // The lip-sync result rides on the line the client sees first, in WhatsApp.
            $lipNote = $lipReport['verdict'] === 'not_matched' ? ' · 👄 Lip-sync NOT matched ('.count($lipReport['moments']).' '.Str::plural('moment', count($lipReport['moments'])).')' : '';
            $integrity = match (true) {
                $mock === null || $onZoom => 'Zoom interview: see the recording',
                $events->isEmpty() => 'Integrity not captured',
                $flags > 0 => "⚠️ {$flags} integrity ".Str::plural('flag', $flags).($possible > 0 ? " + {$possible} possible" : ''),
                $possible > 0 => "{$possible} possible integrity ".Str::plural('flag', $possible).' to check',
                default => 'No integrity flags',
            };

            return EmployerInterviewReport::query()->updateOrCreate(
                ['employer_interview_id' => $interview->id],
                [
                    'tenant_id' => $interview->tenant_id,
                    'employer_job_id' => $job->id,
                    'pdf_path' => "{$base}-report.pdf",
                    'cv_path' => $cvPath,
                    'flags' => $flags,
                    'possible_flags' => $possible,
                    'caption' => Str::limit("📄 {$name} · {$job->title} · {$round} {$score}/100 ({$verdict}) · {$integrity}{$lipNote}", 480),
                    'status' => EmployerInterviewReport::PENDING,
                    'sent_at' => null,
                ],
            );
        });
    }

    /**
     * The lip-sync report: a verdict, the reasons for it, the numbers, a timeline of where the
     * voice and the lips disagreed, and every such moment with its time, photo and a link that
     * opens the recording at that second. "Not matched" is a possible finding from automatic
     * measurements, worded that way; "matched" needs real evidence (a summary with enough
     * speech), and missing data is "not captured", never a pass.
     *
     * @param  Collection<int, MockIntegrityEvent>  $events
     * @return array<string, mixed>
     */
    private function lipReport(Collection $events, ?MockInterview $mock, bool $onZoom, ?string $recordingUrl): array
    {
        $lip = $this->lipSync($events, $onZoom);
        $voice = $this->voiceCheck($events);
        $numbers = $lip['numbers'];
        $match = $numbers['match_pct'] ?? null;
        $lag = $numbers['lag_ms'] ?? null;
        $voices = $events->firstWhere('kind', 'voice_check')?->detail['voices'] ?? null;

        /** @var Collection<int, MockIntegrityEvent> $found */
        $found = $events->filter(fn (MockIntegrityEvent $e) => in_array($e->kind, self::LIP_KINDS, true))->sortBy('elapsed_seconds')->values();

        $verdict = match (true) {
            $onZoom => 'not_checked',
            $found->isNotEmpty() || ($match !== null && $match < self::MATCH_BAD_PCT) => 'not_matched',
            $lip['captured'] && $match !== null && $match >= self::MATCH_OK_PCT && ($lag === null || $lag < 350) => 'matched',
            $lip['captured'] || $voice['checked'] => 'inconclusive',
            default => 'not_captured',
        };

        $headline = match ($verdict) {
            'not_matched' => 'Lip-sync did NOT match the voice',
            'matched' => 'Lip-sync matched the voice',
            'inconclusive' => 'Lip-sync check was inconclusive',
            'not_checked' => 'Lip-sync is not checked on Zoom',
            default => 'Lip-sync was not captured',
        };

        $reasons = [];
        $count = fn (string $kind): int => $found->where('kind', $kind)->count();
        $first = fn (string $kind): string => $this->clock((int) ($found->firstWhere('kind', $kind)?->elapsed_seconds ?? 0));
        if ($count('lip_voice_mismatch') > 0) {
            $reasons[] = 'Speech while the mouth stayed still: '.$count('lip_voice_mismatch').' moment'.($count('lip_voice_mismatch') === 1 ? '' : 's').' (first at '.$first('lip_voice_mismatch').'). Someone else may have been speaking, or the sound was played.';
        }
        if ($count('lip_lag') > 0) {
            $reasons[] = 'The lips started about '.($found->firstWhere('kind', 'lip_lag')?->detail['lag_ms'] ?? $lag).' ms after the voice, phrase after phrase (first at '.$first('lip_lag').'). A person speaking for themselves is usually within about 250 ms. This is what miming to another person\'s words looks like.';
        }
        if ($count('lip_no_voice') > 0) {
            $reasons[] = 'Lips moving with no sound: '.$count('lip_no_voice').' moment'.($count('lip_no_voice') === 1 ? '' : 's').' (first at '.$first('lip_no_voice').'). Miming, or reading silently while someone else answers.';
        }
        if ($count('second_voice') > 0) {
            $reasons[] = 'A second voice answered from '.$first('second_voice').' (the recording has '.($voices ?? 2).' different voices answering).';
        }
        if ($match !== null && $match < self::MATCH_BAD_PCT) {
            $reasons[] = "Lips were moving during only {$match}% of the candidate's speech (a person speaking normally is above about ".self::MATCH_OK_PCT.'%).';
        }

        $stats = [];
        if ($numbers !== null && isset($numbers['speech_s'])) {
            $stats[] = ['label' => 'Speech compared with the camera', 'value' => (int) $numbers['speech_s'].' s', 'note' => ''];
            $stats[] = ['label' => 'Lips moving during speech', 'value' => $match !== null ? "{$match}%" : 'too little to say', 'note' => 'expected: above about '.self::MATCH_OK_PCT.'%'];
            $stats[] = ['label' => 'Lips behind the voice (median)', 'value' => $lag !== null ? "{$lag} ms" : 'too few phrases', 'note' => 'expected: up to about 250 ms'];
            $stats[] = ['label' => 'Face tracked on camera', 'value' => (int) ($numbers['face_pct'] ?? 0).'%', 'note' => ''];
        }
        $stats[] = ['label' => 'Voices answering', 'value' => $voice['checked'] ? (string) ($voices ?? 1) : 'not checked', 'note' => 'expected: 1'];

        $total = max(
            (int) ($mock?->duration_seconds ?? 0),
            (int) ($mock?->started_at !== null && $mock?->completed_at !== null ? $mock->started_at->diffInSeconds($mock->completed_at) : 0),
            ((int) ($found->max('elapsed_seconds') ?? 0)) + 30,
            60,
        );

        $images = collect($this->withImages($found));
        $moments = $found->map(function (MockIntegrityEvent $e, int $i) use ($images, $recordingUrl): array {
            $at = (int) ($e->elapsed_seconds ?? 0);

            return [
                'at' => $this->clock($at),
                'seconds' => $at,
                'kind' => $e->kind,
                'label' => $e->label(),
                'text' => $e->detailText(),
                'image' => $images->get($i)['image'] ?? null,
                'link' => $recordingUrl !== null ? $recordingUrl.'#t='.max(0, $at - 3) : null,
            ];
        })->all();

        return [
            'verdict' => $verdict,
            'headline' => $headline,
            'reasons' => $reasons,
            'stats' => $stats,
            'moments' => $moments,
            'timeline' => $this->timeline($found, $total),
            'total' => $this->clock($total),
            'line' => $lip['line'],
            'captured' => $lip['captured'],
        ];
    }

    /**
     * Sixty equal slices of the interview, each marked where a lip-sync or voice finding falls in it.
     *
     * @param  Collection<int, MockIntegrityEvent>  $found
     * @return list<string> 'ok' | 'lip' | 'voice' per slice
     */
    private function timeline(Collection $found, int $total): array
    {
        $slices = array_fill(0, 60, 'ok');
        $each = max(1, $total / 60);
        foreach ($found as $e) {
            $start = (int) ($e->elapsed_seconds ?? 0);
            $length = max(6, (int) (($e->detail['secs'] ?? 0) ?: 8));
            $mark = $e->kind === 'second_voice' ? 'voice' : 'lip';
            for ($i = (int) floor($start / $each); $i <= min(59, (int) floor(($start + $length) / $each)); $i++) {
                $slices[$i] = $mark;
            }
        }

        return $slices;
    }

    private function clock(int $seconds): string
    {
        return sprintf('%d:%02d', intdiv($seconds, 60), $seconds % 60);
    }

    /**
     * What the lip-sync check found, in words an employer can read. Never "clean" when it
     * was not captured: no summary means the camera model or microphone was not available.
     *
     * @param  Collection<int, MockIntegrityEvent>  $events
     * @return array{captured: bool, line: string, numbers: array<string, int|null>|null}
     */
    private function lipSync(Collection $events, bool $onZoom): array
    {
        if ($onZoom) {
            return ['captured' => false, 'line' => 'Zoom interview: lip-sync is not checked; the recording shows the call.', 'numbers' => null];
        }

        /** @var MockIntegrityEvent|null $summary */
        $summary = $events->firstWhere('kind', 'lip_sync_summary');
        $d = $summary !== null ? (array) ($summary->detail ?? []) : null;
        if ($d === null || ! isset($d['speech_s'])) {
            return ['captured' => false, 'line' => 'Lip-sync was not captured for this interview (the camera model or the microphone was not available). It was neither passed nor failed.', 'numbers' => null];
        }

        $speech = (int) $d['speech_s'];
        $match = $d['match_pct'] ?? null;
        $lag = $d['lag_ms'] ?? null;
        $moments = $events->whereIn('kind', ['lip_voice_mismatch', 'lip_lag', 'lip_no_voice'])->count();

        if ($match === null) {
            return ['captured' => true, 'line' => "Only {$speech} s of the candidate's speech could be compared with the camera, too little to say either way.", 'numbers' => $d];
        }

        $line = "Lips were moving during {$match}% of {$speech} s of the candidate's speech."
            .($lag !== null ? " The lips started about {$lag} ms after the voice on average (a person speaking for themselves is usually within about 250 ms)." : '')
            .($moments > 0 ? " {$moments} moment".($moments === 1 ? '' : 's').' flagged below, with the time and a photo.' : ' No moment looked wrong.');

        return ['captured' => true, 'line' => $line, 'numbers' => $d];
    }

    /**
     * @param  Collection<int, MockIntegrityEvent>  $events
     * @return array{checked: bool, line: string}
     */
    private function voiceCheck(Collection $events): array
    {
        /** @var MockIntegrityEvent|null $check */
        $check = $events->firstWhere('kind', 'voice_check');
        $d = $check !== null ? (array) ($check->detail ?? []) : null;
        if ($d === null || ! isset($d['voices'])) {
            return ['checked' => false, 'line' => 'The voice check (is one person answering?) did not run for this interview.'];
        }

        $voices = (int) $d['voices'];

        return ['checked' => true, 'line' => $voices <= 1
            ? 'Voice check: one voice answered throughout.'
            : "Voice check: {$voices} different voices answered. See the moment flagged below."];
    }

    /** "Python 85", most important first as the grader returned them. @return list<array{label: string, score: int}> */
    private function dimensions(EmployerInterview $interview): array
    {
        $out = [];
        foreach ((array) $interview->dimension_scores as $key => $value) {
            if (is_numeric($value)) {
                $out[] = ['label' => Str::headline((string) $key), 'score' => max(0, min(100, (int) $value))];
            }
        }

        return $out;
    }

    /** @return list<string> */
    private function strings(mixed $value): array
    {
        return array_values(array_slice(array_filter((array) $value, fn ($v) => is_string($v) && trim($v) !== ''), 0, 6));
    }

    /** Question → answer, in order. @return list<array{q: string, a: string}> */
    private function answers(?MockInterview $mock): array
    {
        if ($mock === null) {
            return [];
        }

        $pairs = [];
        $question = null;
        foreach ($mock->turns->sortBy('id') as $turn) {
            if ($turn->role === 'interviewer') {
                $question = $turn->body;
            } elseif ($turn->role === 'candidate' && $question !== null) {
                $pairs[] = ['q' => Str::limit(trim($question), 400), 'a' => Str::limit(trim($turn->body), 900)];
                $question = null;
            }
        }

        return array_slice($pairs, 0, self::MAX_ANSWERS);
    }

    /**
     * Events with their still inlined (dompdf reads data URIs, never a URL).
     *
     * @param  Collection<int, MockIntegrityEvent>  $events
     * @return list<array{label: string, detail: string|null, severity: string, at: string, image: string|null}>
     */
    private function withImages(Collection $events): array
    {
        $disk = Storage::disk(self::DISK);

        return $events->map(function (MockIntegrityEvent $e) use ($disk): array {
            $image = null;
            if ($e->image_path !== null && $disk->exists($e->image_path)) {
                $image = 'data:image/jpeg;base64,'.base64_encode((string) $disk->get($e->image_path));
            }
            $at = $e->elapsed_seconds !== null
                ? sprintf('%d:%02d in', intdiv($e->elapsed_seconds, 60), $e->elapsed_seconds % 60)
                : $e->occurred_at->setTimezone('Asia/Kolkata')->format('g:i:s A');

            return ['label' => $e->label(), 'detail' => $e->detailText(), 'severity' => $e->severity, 'at' => $at, 'image' => $image];
        })->values()->all();
    }

    /** The candidate's CV profile as a PDF, or null when they have none. */
    private function cv(User $candidate, string $name, EmployerJob $job, string $path): ?string
    {
        if (! CvProfile::query()->where('user_id', $candidate->id)->exists()) {
            return null;
        }

        $data = CvProfile::dataFor($candidate);
        $pdf = Pdf::loadView('pdf.cv', [
            'name' => $name,
            'role' => $job->title,
            'summary' => is_string($data['summary']) ? $data['summary'] : null,
            'skills' => array_values(array_filter((array) $data['skills'], 'is_string')),
            'experience' => array_values(array_filter((array) $data['experience'], 'is_array')),
            'projects' => array_values(array_filter((array) $data['projects'], 'is_array')),
            'education' => array_values(array_filter((array) $data['education'], 'is_array')),
            'certifications' => array_values(array_filter((array) $data['certifications'], fn ($c) => is_string($c) || is_array($c))),
        ])->setPaper('a4')->setOption('isFontSubsettingEnabled', true);

        Storage::disk(self::DISK)->put($path, $pdf->output(), 'private');

        return $path;
    }
}
