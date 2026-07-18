<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Actions\Market\GenerateSyllabusRecommendation;
use App\Events\CurriculumChanged;
use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\SyllabusRecommendation;
use App\Support\Audit\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Syllabus Recommendation Reports (PRD §6.21). Trainer/admin surface: generate a
 * fresh evidence-backed report on demand, then approve or reject its items.
 * Approving records the decision and nudges syllabus regeneration; the trainer
 * applies the actual curriculum edits through the curriculum tools (running
 * batches unaffected — next batches get the updated syllabus).
 */
final class SyllabusRecommendationController extends Controller
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function index(Request $request): JsonResponse
    {
        $courseId = $request->integer('course_id') ?: null;

        $reports = SyllabusRecommendation::query()
            ->when($courseId !== null, fn ($q) => $q->where('course_id', $courseId))
            ->with(['course:id,name', 'reviewer:id,name'])
            ->orderByRaw("CASE status WHEN 'pending' THEN 0 ELSE 1 END")
            ->orderByDesc('id')
            ->limit(50)
            ->get()
            ->map(fn (SyllabusRecommendation $r) => [
                'id' => $r->id,
                'course' => $r->course?->name,
                'course_id' => $r->course_id,
                'status' => $r->status,
                'source' => $r->source,
                'content_source' => $r->content_source,
                'summary' => $r->summary,
                'items' => $r->items,
                'evidence' => $r->evidence,
                'market_sample' => $r->market_sample,
                'reviewer' => $r->reviewer?->name,
                'review_note' => $r->review_note,
                'created_at' => $r->created_at?->toIso8601String(),
            ]);

        return response()->json([
            'data' => [
                'courses' => Course::query()->orderBy('name')->get(['id', 'name']),
                'reports' => $reports,
            ],
        ]);
    }

    public function generate(Request $request, Course $course, GenerateSyllabusRecommendation $generate): JsonResponse
    {
        $report = $generate->handle($request->user(), $course);

        $this->audit->log('syllabus_recommendation.generated', $report, [
            'course_id' => $course->id,
            'items' => count($report->items),
            'content_source' => $report->content_source,
        ], $request->user());

        return response()->json(['data' => ['id' => $report->id, 'items' => $report->items, 'content_source' => $report->content_source]], 201);
    }

    public function approve(Request $request, SyllabusRecommendation $recommendation): JsonResponse
    {
        abort_if($recommendation->status !== SyllabusRecommendation::STATUS_PENDING, 422, 'This report has already been reviewed.');

        $recommendation->update([
            'status' => SyllabusRecommendation::STATUS_APPROVED,
            'reviewed_by' => $request->user()->id,
            'reviewed_at' => now(),
            'review_note' => $request->string('note')->trim()->value() ?: null,
        ]);

        // Nudge syllabus regeneration (PRD §6.21). Idempotent: the listener no-ops
        // if the curriculum hash is unchanged, so this never churns on its own —
        // the actual regen fires once the trainer applies the curriculum edits.
        CurriculumChanged::dispatch((int) $recommendation->course_id, (int) $recommendation->tenant_id);

        $this->audit->log('syllabus_recommendation.approved', $recommendation, [
            'course_id' => $recommendation->course_id,
        ], $request->user());

        return response()->json(['data' => ['status' => $recommendation->status]]);
    }

    public function reject(Request $request, SyllabusRecommendation $recommendation): JsonResponse
    {
        abort_if($recommendation->status !== SyllabusRecommendation::STATUS_PENDING, 422, 'This report has already been reviewed.');

        $recommendation->update([
            'status' => SyllabusRecommendation::STATUS_REJECTED,
            'reviewed_by' => $request->user()->id,
            'reviewed_at' => now(),
            'review_note' => $request->string('note')->trim()->value() ?: null,
        ]);

        $this->audit->log('syllabus_recommendation.rejected', $recommendation, [
            'course_id' => $recommendation->course_id,
        ], $request->user());

        return response()->json(['data' => ['status' => $recommendation->status]]);
    }
}
