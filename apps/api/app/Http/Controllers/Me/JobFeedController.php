<?php

declare(strict_types=1);

namespace App\Http\Controllers\Me;

use App\Actions\JobFeed\ApplyToFeedItem;
use App\Actions\JobFeed\GenerateJobPrepQuestions;
use App\Actions\Mocks\StartJobMock;
use App\Http\Controllers\Controller;
use App\Models\CvDocument;
use App\Models\JobFeedItem;
use App\Models\JobFeedSave;
use App\Models\User;
use App\Support\Entitlements\EntitlementService;
use App\Support\JobFeed\ConfidenceScorer;
use App\Support\JobFeed\JobKitAccess;
use App\Support\JobFeed\JobsForYou;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * The student "Jobs for You" feed (PRD §6.22, ADR 0048): relevance-ranked
 * openings with a match-% badge, the "why/gap" explanation, an interview
 * confidence score, per-JD prep questions, a quick JD mock, and save/dismiss.
 * Reads only the viewer's own tenant + interactions.
 */
final class JobFeedController extends Controller
{
    public function index(Request $request, JobsForYou $feed): JsonResponse
    {
        $rows = $feed->for($request->user());
        $confidence = new ConfidenceScorer($request->user());
        $kit = $this->kit($request->user());

        return app(TenantContext::class)->run($request->user()->tenant, fn (): JsonResponse => response()->json([
            'data' => array_map(function (array $row) use ($confidence, $kit) {
                $c = $confidence->for((int) $row['match_pct']);

                return [
                    'id' => $row['item']->id,
                    'title' => $row['item']->title,
                    'company' => $row['item']->company,
                    'location' => $row['item']->location,
                    'work_mode' => $row['item']->work_mode,
                    'source_kind' => $row['item']->source_kind,
                    'apply_url' => $row['item']->apply_url,
                    'posted_at' => $row['item']->posted_at?->toDateString(),
                    'match_pct' => $row['match_pct'],
                    'matched' => $row['matched'],
                    'gap' => $row['gap'],
                    'saved' => $row['saved'],
                    'confidence_pct' => $c['confidence_pct'],
                    'confidence_based_on' => $c['based_on'],
                    'has_mock_signal' => $confidence->hasMock(),
                    'unlocked' => $kit->unlockedFor($row['item']),
                ];
            }, $rows),
            'kit' => [
                'credits' => $kit->creditBalance(),
                'offers' => $kit->offers(),
            ],
        ]));
    }

    /** How many questions a locked viewer sees for free. */
    private const SAMPLE_SIZE = 3;

    /**
     * Potential interview questions for one posting — real-bank questions for
     * the role first, then JD-derived ones. Generated once, cached on the item.
     * Locked viewers get a sample of three plus the honest provenance counts
     * and the kit offers; unlocking is a job_kit credit (ADR 0048).
     */
    public function prep(Request $request, JobFeedItem $item, GenerateJobPrepQuestions $generate): JsonResponse
    {
        $user = $request->user();
        abort_unless($item->tenant_id === $user->tenant_id && $item->status === JobFeedItem::STATUS_ACTIVE, 404);

        return app(TenantContext::class)->run($user->tenant, function () use ($generate, $user, $item): JsonResponse {
            $questions = $generate->handle($user, $item);
            $kit = $this->kit($user);
            $unlocked = $kit->unlockedFor($item);
            $realCount = count(array_filter($questions, fn (array $q) => $q['source'] === 'real'));

            return response()->json(['data' => [
                'unlocked' => $unlocked,
                'questions' => $unlocked ? $questions : array_slice($questions, 0, self::SAMPLE_SIZE),
                'total' => count($questions),
                // Honest provenance: how many were actually asked in real
                // interviews for this role (never a made-up figure).
                'real_count' => $realCount,
                'offers' => $unlocked ? [] : $kit->offers(),
                'credits' => $unlocked ? null : $kit->creditBalance(),
            ]]);
        });
    }

    /**
     * Spend one Interview Kit credit on this posting. Answers 402 with the kit
     * offers when the wallet is empty (mirrors the mentor-booking pattern).
     */
    public function unlock(Request $request, JobFeedItem $item): JsonResponse
    {
        $user = $request->user();
        abort_unless($item->tenant_id === $user->tenant_id && $item->status === JobFeedItem::STATUS_ACTIVE, 404);

        return app(TenantContext::class)->run($user->tenant, function () use ($user, $item): JsonResponse {
            $kit = $this->kit($user);

            try {
                $kit->unlock($item);
            } catch (ValidationException $e) {
                if (str_contains($e->getMessage(), 'credit')) {
                    return response()->json([
                        'error' => [
                            'code' => 'no_job_kit_credits',
                            'message' => 'Buy an Interview Kit to unlock this job\'s full prep.',
                            'offers' => $kit->offers(),
                        ],
                    ], 402);
                }

                throw $e;
            }

            return response()->json(['data' => ['unlocked' => true]]);
        });
    }

    /**
     * Quick mock for this job: a text mock whose interviewer stays on this
     * posting's JD. Same engine, scorecard and PRI blend as course mocks.
     */
    public function mock(Request $request, JobFeedItem $item, StartJobMock $start): JsonResponse
    {
        $user = $request->user();
        abort_unless($item->tenant_id === $user->tenant_id && $item->status === JobFeedItem::STATUS_ACTIVE, 404);

        return app(TenantContext::class)->run($user->tenant, function () use ($start, $user, $item): JsonResponse {
            $kit = $this->kit($user);
            if (! $kit->unlockedFor($item)) {
                return response()->json([
                    'error' => [
                        'code' => 'job_kit_required',
                        'message' => 'JD mocks are part of the Interview Kit for this job.',
                        'offers' => $kit->offers(),
                    ],
                ], 402);
            }

            $interview = $start->handle($user, $item);

            return response()->json(['data' => ['mock_id' => $interview->id]], 201);
        });
    }

    private function kit(User $user): JobKitAccess
    {
        return new JobKitAccess($user, app(EntitlementService::class));
    }

    public function apply(Request $request, JobFeedItem $item, ApplyToFeedItem $apply): JsonResponse
    {
        $user = $request->user();
        abort_unless($item->tenant_id === $user->tenant_id && $item->status === JobFeedItem::STATUS_ACTIVE, 404);

        $application = $apply->handle($user, $item);
        $cv = CvDocument::query()->find($application->cv_document_id);

        return response()->json(['data' => [
            'application_id' => $application->id,
            'cv_document_id' => $application->cv_document_id,
            'ats_score' => $cv?->ats['score'] ?? null,
            'apply_url' => $item->apply_url,
        ]], 201);
    }

    /**
     * Apply Copilot (PRD §6.22) — human-in-the-loop ATS pre-fill. Feature-flagged
     * Phase 5 extension point; off by default and never auto-submits.
     */
    public function copilot(Request $request, JobFeedItem $item): JsonResponse
    {
        abort_unless($item->tenant_id === $request->user()->tenant_id, 404);
        abort_unless((bool) config('features.apply_copilot', false), 403, 'Apply Copilot is not enabled yet.');

        // Extension point: an agent pre-fills the external ATS form from the
        // student's profile + tailored CV; the student reviews and confirms.
        // Never submits autonomously.
        return response()->json(['data' => ['status' => 'not_implemented']], 501);
    }

    public function save(Request $request, JobFeedItem $item): JsonResponse
    {
        return $this->setState($request, $item, JobFeedSave::STATE_SAVED);
    }

    public function dismiss(Request $request, JobFeedItem $item): JsonResponse
    {
        return $this->setState($request, $item, JobFeedSave::STATE_DISMISSED);
    }

    private function setState(Request $request, JobFeedItem $item, string $state): JsonResponse
    {
        $user = $request->user();
        abort_unless($item->tenant_id === $user->tenant_id, 404);

        JobFeedSave::query()->updateOrCreate(
            ['user_id' => $user->id, 'job_feed_item_id' => $item->id],
            ['tenant_id' => $user->tenant_id, 'state' => $state],
        );

        return response()->json(['data' => ['state' => $state]]);
    }
}
