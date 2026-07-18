<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\HiringPartnerFeedback;
use App\Models\JobApplication;
use App\Support\Audit\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;

/**
 * Hiring-partner feedback admin surface (PRD §6.21). Placement team requests
 * feedback for a candidate's interview and gets a shareable public link to send
 * the company; submitted feedback (esp. the gap signal) appears in the list.
 */
final class HiringPartnerFeedbackController extends Controller
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function index(): JsonResponse
    {
        $records = HiringPartnerFeedback::query()
            ->with('application.student:id,name')
            ->orderByDesc('id')
            ->limit(100)
            ->get()
            ->map(fn (HiringPartnerFeedback $f) => [
                'id' => $f->id,
                'company' => $f->company,
                'role_title' => $f->role_title,
                'candidate' => $f->application?->student?->name,
                'status' => $f->status,
                'overall' => $f->overall,
                'would_hire' => $f->would_hire,
                'ratings' => $f->ratings,
                'strengths' => $f->strengths,
                'gaps' => $f->gaps,
                'link' => $f->status === HiringPartnerFeedback::STATUS_REQUESTED ? $this->publicLink($f->token) : null,
                'submitted_at' => $f->submitted_at?->toIso8601String(),
                'created_at' => $f->created_at?->toIso8601String(),
            ]);

        return response()->json(['data' => $records]);
    }

    public function request(Request $request, AuditLogger $audit): JsonResponse
    {
        $data = $request->validate([
            'job_application_id' => ['required', 'integer'],
            'company' => ['nullable', 'string', 'max:190'],
            'role_title' => ['nullable', 'string', 'max:190'],
        ]);

        $application = JobApplication::query()->with('posting')->findOrFail((int) $data['job_application_id']);

        $feedback = HiringPartnerFeedback::query()->create([
            'tenant_id' => $application->tenant_id,
            'job_application_id' => $application->id,
            'course_id' => $application->posting?->course_id,
            'company' => $data['company'] ?? ($application->posting?->company ?? 'The company'),
            'role_title' => $data['role_title'] ?? ($application->posting?->title ?? 'the role'),
            'token' => HiringPartnerFeedback::newToken(),
            'status' => HiringPartnerFeedback::STATUS_REQUESTED,
            'requested_by' => $request->user()->id,
        ]);

        $this->audit->log('hiring_partner_feedback.requested', $feedback, [
            'application_id' => $application->id,
        ], $request->user());

        return response()->json(['data' => [
            'id' => $feedback->id,
            'link' => $this->publicLink($feedback->token),
        ]], 201);
    }

    private function publicLink(string $token): string
    {
        return URL::to('/partner-feedback/'.$token);
    }
}
