<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Actions\Interviews\IngestInterviewTranscript;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\IngestTranscriptRequest;
use App\Models\InterviewTranscript;
use App\Models\RealInterviewQuestion;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Placement-team surface for Real Interview Intelligence (PRD §6.6):
 * transcript uploads + pipeline status, the review queue (approve/reject/
 * batch-approve), and bank analytics (frequency by topic/role, monthly
 * trend) that later feeds Curriculum Intelligence (§6.21).
 */
final class InterviewBankController extends Controller
{
    public function __construct(private readonly IngestInterviewTranscript $ingest) {}

    public function transcripts(): JsonResponse
    {
        return response()->json([
            'data' => InterviewTranscript::query()
                ->with(['uploader:id,name', 'course:id,name'])
                ->orderByDesc('id')
                ->limit(50)
                ->get()
                ->map(fn (InterviewTranscript $t) => [
                    'id' => $t->id,
                    'source' => $t->source,
                    'original_name' => $t->original_name,
                    'role_title' => $t->role_title,
                    'course' => $t->course?->name,
                    'uploader' => $t->uploader?->name,
                    'status' => $t->status,
                    'parse_error' => $t->parse_error,
                    'questions_found' => $t->questions_found,
                    'created_at' => $t->created_at?->toIso8601String(),
                ]),
        ]);
    }

    public function upload(IngestTranscriptRequest $request): JsonResponse
    {
        $transcript = $this->ingest->handle(
            $request->user(),
            [
                'source' => $request->string('source')->toString(),
                'text' => $request->input('text'),
                'course_id' => $request->filled('course_id') ? $request->integer('course_id') : null,
                'role_title' => $request->input('role_title'),
            ],
            $request->file('file'),
        );

        return response()->json(['data' => ['id' => $transcript->id, 'status' => $transcript->status]], 202);
    }

    public function index(): JsonResponse
    {
        $pending = RealInterviewQuestion::query()
            ->where('status', RealInterviewQuestion::STATUS_PENDING)
            ->with('course:id,name')
            ->orderByDesc('id')
            ->limit(100)
            ->get();

        $approved = RealInterviewQuestion::query()
            ->where('status', RealInterviewQuestion::STATUS_APPROVED)
            ->orderByDesc('asked_count')
            ->limit(50)
            ->get();

        return response()->json([
            'data' => [
                'pending' => $pending->map(fn (RealInterviewQuestion $q) => $this->row($q)),
                'approved' => $approved->map(fn (RealInterviewQuestion $q) => $this->row($q)),
                'analytics' => $this->analytics(),
            ],
        ]);
    }

    public function approve(Request $request, RealInterviewQuestion $question): JsonResponse
    {
        $question->update([
            'status' => RealInterviewQuestion::STATUS_APPROVED,
            'approved_by' => $request->user()->id,
            'approved_at' => now(),
        ]);

        return response()->json(['data' => $this->row($question->refresh())]);
    }

    public function reject(RealInterviewQuestion $question): JsonResponse
    {
        $question->update(['status' => RealInterviewQuestion::STATUS_REJECTED]);

        return response()->json(['data' => $this->row($question->refresh())]);
    }

    /** Batch-approve: explicit ids, or every pending high-confidence parse. */
    public function approveBatch(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'ids' => ['sometimes', 'array'],
            'ids.*' => ['integer'],
            'high_confidence' => ['sometimes', 'boolean'],
        ]);

        $query = RealInterviewQuestion::query()->where('status', RealInterviewQuestion::STATUS_PENDING);

        if (! empty($validated['ids'])) {
            $query->whereIn('id', $validated['ids']);
        } elseif (($validated['high_confidence'] ?? false) === true) {
            $query->where('confidence', 'high');
        } else {
            return response()->json(['error' => ['message' => 'Pass ids or high_confidence.']], 422);
        }

        $count = $query->update([
            'status' => RealInterviewQuestion::STATUS_APPROVED,
            'approved_by' => $request->user()->id,
            'approved_at' => now(),
        ]);

        return response()->json(['data' => ['approved' => $count]]);
    }

    /**
     * @return array<string, mixed>
     */
    private function row(RealInterviewQuestion $q): array
    {
        return [
            'id' => $q->id,
            'question' => $q->question,
            'role_title' => $q->role_title,
            'course' => $q->course?->name,
            'topic_tags' => $q->topic_tags,
            'difficulty' => $q->difficulty,
            'round_type' => $q->round_type,
            'follow_ups' => $q->follow_ups,
            'strong_answer' => $q->strong_answer,
            'struggle_points' => $q->struggle_points,
            'confidence' => $q->confidence,
            'status' => $q->status,
            'asked_count' => $q->asked_count,
            'last_seen_at' => $q->last_seen_at?->toIso8601String(),
        ];
    }

    /**
     * Frequency by topic/role + 6-month trend, from approved rows only —
     * the same signal the gap report and Curriculum Intelligence read.
     *
     * @return array<string, mixed>
     */
    private function analytics(): array
    {
        $approved = RealInterviewQuestion::query()
            ->where('status', RealInterviewQuestion::STATUS_APPROVED)
            ->get(['role_title', 'topic_tags', 'asked_count', 'last_seen_at']);

        $byTopic = [];
        $byRole = [];
        foreach ($approved as $q) {
            $byRole[$q->role_title] = ($byRole[$q->role_title] ?? 0) + $q->asked_count;
            foreach ($q->topic_tags as $tag) {
                $byTopic[$tag] = ($byTopic[$tag] ?? 0) + $q->asked_count;
            }
        }
        arsort($byTopic);
        arsort($byRole);

        $trend = RealInterviewQuestion::query()
            ->where('status', RealInterviewQuestion::STATUS_APPROVED)
            ->where('last_seen_at', '>=', now()->subMonths(6)->startOfMonth())
            ->get(['last_seen_at', 'asked_count'])
            ->groupBy(fn (RealInterviewQuestion $q) => $q->last_seen_at?->format('Y-m'))
            ->map(fn ($group) => $group->sum('asked_count'))
            ->sortKeys();

        return [
            'total_approved' => $approved->count(),
            'by_topic' => array_slice($byTopic, 0, 12, preserve_keys: true),
            'by_role' => array_slice($byRole, 0, 8, preserve_keys: true),
            'monthly_trend' => $trend,
        ];
    }
}
