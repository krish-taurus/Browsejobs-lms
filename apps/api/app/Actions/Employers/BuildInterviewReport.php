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
                'flagged' => $this->withImages($events->where('severity', '!=', MockIntegrityEvent::INFO)->values()),
                'checkIns' => $this->withImages($events->where('severity', MockIntegrityEvent::INFO)->values()),
                'flagCounts' => $events->where('severity', '!=', MockIntegrityEvent::INFO)->groupBy('kind')->map->count(),
                'flags' => $flags,
                'possible' => $possible,
                // Every room that checks integrity also logs check-ins: no events at all means not captured, never "clean".
                'proctored' => $mock !== null && ! $onZoom && $events->isNotEmpty(),
                'onZoom' => $onZoom,
                'hasRecording' => $mock?->recording_url !== null,
                // Skills in the plan nobody was asked about: shown as "not assessed", never as a score.
                'notCovered' => array_values(array_filter((array) ($mock?->scorecard['not_covered'] ?? []), 'is_string')),
                // A link the client can open without logging in (signed, 30 days).
                'recordingUrl' => $mock !== null ? RecordingLink::for($mock) : null,
                'profileUrl' => rtrim((string) config('app.frontend_url'), '/')."/employer/jobs/{$job->id}/candidates/{$application->id}",
                'generatedOn' => now('Asia/Kolkata')->format('j M Y, g:i A'),
            ])->setPaper('a4')->setOption('isFontSubsettingEnabled', true);

            $base = "interview-reports/{$interview->tenant_id}/{$interview->id}";
            Storage::disk(self::DISK)->put("{$base}-report.pdf", $pdf->output(), 'private');
            $cvPath = $candidate !== null ? $this->cv($candidate, $name, $job, "{$base}-cv.pdf") : null;

            $verdict = $score >= $passMark ? 'passed' : "below your pass mark of {$passMark}";
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
                    'caption' => Str::limit("📄 {$name} · {$job->title} · {$round} {$score}/100 ({$verdict}) · {$integrity}", 480),
                    'status' => EmployerInterviewReport::PENDING,
                    'sent_at' => null,
                ],
            );
        });
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
     * @return list<array{label: string, severity: string, at: string, image: string|null}>
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

            return ['label' => $e->label(), 'severity' => $e->severity, 'at' => $at, 'image' => $image];
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
