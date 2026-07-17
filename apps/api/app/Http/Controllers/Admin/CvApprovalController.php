<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CvDocument;
use App\Support\Audit\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Placement-officer CV approval (PRD §6.7): drafts queue up, approval marks
 * a version placement-ready. Audit-logged — approval puts the company's
 * name behind the document.
 */
final class CvApprovalController extends Controller
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function index(): JsonResponse
    {
        $pending = CvDocument::query()
            ->where('status', CvDocument::STATUS_DRAFT)
            ->with('student:id,name')
            ->orderByDesc('id')
            ->limit(50)
            ->get();

        $approved = CvDocument::query()
            ->where('status', CvDocument::STATUS_APPROVED)
            ->with('student:id,name')
            ->orderByDesc('approved_at')
            ->limit(20)
            ->get();

        $map = fn (CvDocument $cv) => [
            'id' => $cv->id,
            'student' => $cv->student?->name,
            'title' => $cv->title,
            'version' => $cv->version,
            'source' => $cv->source,
            'parse_score' => $cv->ats['parse_score'] ?? null,
            'status' => $cv->status,
            'content' => $cv->content,
            'created_at' => $cv->created_at?->toIso8601String(),
        ];

        return response()->json(['data' => [
            'pending' => $pending->map($map),
            'approved' => $approved->map($map),
        ]]);
    }

    public function approve(Request $request, CvDocument $cv): JsonResponse
    {
        $cv->update([
            'status' => CvDocument::STATUS_APPROVED,
            'approved_by' => $request->user()->id,
            'approved_at' => now(),
        ]);

        $this->audit->log('cv.approved', $cv, ['version' => $cv->version], $request->user());

        return response()->json(['data' => ['id' => $cv->id, 'status' => $cv->status]]);
    }
}
