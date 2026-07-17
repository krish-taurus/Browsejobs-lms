<?php

declare(strict_types=1);

namespace App\Actions\Placement;

use App\Actions\Interviews\IngestInterviewTranscript;
use App\Models\JobApplication;
use App\Models\JobApplicationRound;

/**
 * Debrief capture after a real interview round (PRD §6.11). The round is
 * the student's record; when they consent, the debrief text flows into the
 * P4.2 transcript pipeline (source 'debrief') — parse → anonymise → review
 * queue — so every real interview enriches the bank.
 */
final readonly class RecordInterviewRound
{
    public function __construct(private IngestInterviewTranscript $ingest) {}

    /**
     * @param  array{round_type: string|null, happened_on: string, outcome: string, debrief: string|null, share_debrief: bool}  $input
     */
    public function handle(JobApplication $application, array $input): JobApplicationRound
    {
        $round = JobApplicationRound::query()->create([
            'tenant_id' => $application->tenant_id,
            'job_application_id' => $application->id,
            'round_no' => (int) $application->rounds()->max('round_no') + 1,
            'round_type' => $input['round_type'],
            'happened_on' => $input['happened_on'],
            'outcome' => $input['outcome'],
            'debrief' => $input['debrief'],
        ]);

        $debrief = trim((string) ($input['debrief'] ?? ''));

        if ($input['share_debrief'] && $debrief !== '' && $application->student !== null) {
            $application->loadMissing('posting');

            $transcript = $this->ingest->handle($application->student, [
                'source' => 'debrief',
                'text' => "Interview debrief — round {$round->round_no}"
                    .($round->round_type !== null ? " ({$round->round_type})" : '')
                    .", outcome: {$round->outcome}.\n\n{$debrief}",
                'course_id' => $application->posting?->course_id,
                'role_title' => $application->posting?->title,
            ]);

            $round->update(['interview_transcript_id' => $transcript->id]);
        }

        return $round->refresh();
    }
}
