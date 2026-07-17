<?php

declare(strict_types=1);

namespace App\Actions\Reports;

use App\Enums\BatchMemberStatus;
use App\Enums\ReportType;
use App\Models\InAppNotification;
use App\Models\LiveSession;
use App\Models\Report;
use App\Models\StudentScore;
use App\Models\Topic;
use App\Support\Messaging\Messenger;
use App\Support\Reports\ReportNarrator;
use App\Support\Tenancy\TenantContext;

/**
 * Generates a trainer's pre-class brief for a live session (PRD §6.10): the batch, the
 * topic, and who to watch (at-risk students from the P3.2 scores). Narrated (billed to
 * the trainer, fail-safe) and delivered. Skips when the batch has no trainer. Idempotent
 * per session.
 */
final readonly class GenerateTrainerBrief
{
    /** Students at/above this dropout risk are flagged in the brief. */
    private const AT_RISK_THRESHOLD = 50;

    public function __construct(
        private ReportNarrator $narrator,
        private Messenger $messenger,
    ) {}

    public function handle(LiveSession $session): ?Report
    {
        return app(TenantContext::class)->run($session->tenant, function () use ($session): ?Report {
            $trainer = $session->batch?->trainer;
            if ($trainer === null) {
                return null; // no trainer assigned — nothing to brief
            }

            $occupying = array_map(fn (BatchMemberStatus $s) => $s->value, BatchMemberStatus::occupying());
            $memberIds = $session->batch->members()->whereIn('status', $occupying)->pluck('user_id');

            $atRisk = StudentScore::query()
                ->whereIn('user_id', $memberIds)
                ->where(fn ($q) => $q->where('risk_dropout', '>=', self::AT_RISK_THRESHOLD)->orWhereNotNull('red_flags'))
                ->with('student:id,name')
                ->orderByDesc('risk_dropout')
                ->get()
                ->filter(fn (StudentScore $s) => $s->risk_dropout >= self::AT_RISK_THRESHOLD || ($s->red_flags ?? []) !== [])
                ->map(fn (StudentScore $s) => ($s->student?->name ?? 'A student').' (risk '.$s->risk_dropout.')')
                ->take(6)
                ->implode(', ');

            $topicName = $session->topic_id !== null
                ? (Topic::query()->whereKey($session->topic_id)->value('name') ?? $session->title)
                : $session->title;

            $narration = $this->narrator->narrate($trainer, ReportType::TrainerBrief, [
                'name' => $trainer->name,
                'batch' => 'Batch '.($session->batch->number ?? ''),
                'topic' => (string) $topicName,
                'roster_size' => (string) $memberIds->count(),
                'at_risk' => $atRisk,
            ]);

            $report = Report::query()->updateOrCreate(
                ['recipient_id' => $trainer->id, 'type' => ReportType::TrainerBrief->value, 'subject_id' => $session->id],
                [
                    'tenant_id' => $session->tenant_id,
                    'subject_type' => LiveSession::class,
                    'title' => 'Pre-class brief — '.$topicName,
                    'narrative' => $narration['narrative'],
                    'data' => ['roster_size' => $memberIds->count(), 'at_risk' => $atRisk, 'topic' => $topicName],
                    'ai_event_id' => $narration['ai_event_id'],
                ],
            );

            if ($report->wasRecentlyCreated) {
                $this->messenger->send($trainer, 'trainer_brief', [
                    'name' => $trainer->name,
                    'batch' => 'Batch '.($session->batch->number ?? ''),
                    'body' => $narration['narrative'],
                ]);

                InAppNotification::query()->create([
                    'tenant_id' => $session->tenant_id,
                    'user_id' => $trainer->id,
                    'title' => 'Pre-class brief',
                    'body' => $narration['narrative'],
                    'url' => '/admin/batches',
                ]);
            }

            return $report;
        });
    }
}
