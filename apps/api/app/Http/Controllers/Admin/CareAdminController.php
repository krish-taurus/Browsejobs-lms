<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Enums\BatchMemberStatus;
use App\Http\Controllers\Controller;
use App\Models\EnrolmentPause;
use App\Models\InAppNotification;
use App\Models\InterventionAlert;
use App\Models\NpsPulse;
use App\Models\OnboardingChecklist;
use App\Support\Audit\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Counselor care workspace (PRD §6.20): intervention alerts, detractor
 * responses, pause approvals (audited — they move enrolment status), and
 * the week-1 onboarding tracker. Behind can:manage-leads (counselor work).
 */
final class CareAdminController extends Controller
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function index(): JsonResponse
    {
        return response()->json(['data' => [
            'alerts' => InterventionAlert::query()
                ->where('status', InterventionAlert::STATUS_OPEN)
                ->with('student:id,name,phone')
                ->orderByDesc('id')->limit(50)->get()
                ->map(fn (InterventionAlert $a) => [
                    'id' => $a->id, 'student' => $a->student?->name, 'phone' => $a->student?->phone,
                    'signals' => $a->signals, 'created_at' => $a->created_at?->toIso8601String(),
                ]),
            'detractors' => NpsPulse::query()
                ->where('routed', 'rescue')
                ->with('student:id,name,phone')
                ->orderByDesc('responded_at')->limit(30)->get()
                ->map(fn (NpsPulse $p) => [
                    'id' => $p->id, 'student' => $p->student?->name, 'phone' => $p->student?->phone,
                    'milestone' => $p->milestone, 'score' => $p->score, 'comment' => $p->comment,
                    'responded_at' => $p->responded_at?->toIso8601String(),
                ]),
            'nps' => [
                'responses' => NpsPulse::query()->whereNotNull('score')->count(),
                'promoters' => NpsPulse::query()->where('score', '>=', 9)->count(),
                'detractors' => NpsPulse::query()->whereNotNull('score')->where('score', '<=', 6)->count(),
            ],
            'pauses' => EnrolmentPause::query()
                ->with('student:id,name')
                ->orderByDesc('id')->limit(30)->get()
                ->map(fn (EnrolmentPause $p) => [
                    'id' => $p->id, 'student' => $p->student?->name, 'status' => $p->status,
                    'reason' => $p->reason, 'paused_until' => $p->paused_until?->toDateString(),
                ]),
            'onboarding' => OnboardingChecklist::query()
                ->with('student:id,name,phone')
                ->whereNull('week1_checkin_at')
                ->orderBy('created_at')->limit(50)->get()
                ->map(fn (OnboardingChecklist $c) => [
                    'id' => $c->id, 'student' => $c->student?->name, 'phone' => $c->student?->phone,
                    'welcome_call_at' => $c->welcome_call_at?->toIso8601String(),
                    'platform_tour_at' => $c->platform_tour_at?->toIso8601String(),
                    'week1_checkin_at' => $c->week1_checkin_at?->toIso8601String(),
                ]),
        ]]);
    }

    public function handleAlert(Request $request, InterventionAlert $alert): JsonResponse
    {
        $validated = $request->validate(['resolution' => ['required', 'string', 'max:500']]);

        $alert->update([
            'status' => InterventionAlert::STATUS_HANDLED,
            'handled_by' => $request->user()->id,
            'handled_at' => now(),
            'resolution' => $validated['resolution'],
        ]);

        return response()->json(['data' => ['id' => $alert->id, 'status' => $alert->status]]);
    }

    public function decidePause(Request $request, EnrolmentPause $pause): JsonResponse
    {
        $validated = $request->validate([
            'decision' => ['required', 'in:approved,rejected'],
            'paused_until' => ['required_if:decision,approved', 'nullable', 'date', 'after:today',
                'before:'.now()->addDays((int) config('retention.pause.max_days', 90) + 1)->toDateString()],
        ]);

        abort_if($pause->status !== EnrolmentPause::STATUS_REQUESTED, 422, 'This request was already decided.');

        $pause->update([
            'status' => $validated['decision'],
            'decided_by' => $request->user()->id,
            'decided_at' => now(),
            'paused_until' => $validated['paused_until'] ?? null,
        ]);

        if ($validated['decision'] === EnrolmentPause::STATUS_APPROVED) {
            $pause->member?->update(['status' => BatchMemberStatus::Paused->value]);
        }

        $this->audit->log("enrolment_pause.{$validated['decision']}", $pause, [
            'paused_until' => $pause->paused_until?->toDateString(),
        ], $request->user());

        if ($pause->student !== null) {
            InAppNotification::query()->create([
                'tenant_id' => $pause->tenant_id,
                'user_id' => $pause->student->id,
                'title' => $validated['decision'] === 'approved'
                    ? 'Your enrolment pause is approved'
                    : 'About your pause request',
                'body' => $validated['decision'] === 'approved'
                    ? 'Your seat is safe until '.$pause->paused_until?->format('j M Y').'. Your counselor will help you rejoin a batch when you are ready.'
                    : 'Your counselor will call to talk through the options.',
                'url' => '/checkin',
            ]);
        }

        return response()->json(['data' => ['id' => $pause->id, 'status' => $pause->status]]);
    }

    public function updateOnboarding(Request $request, OnboardingChecklist $checklist): JsonResponse
    {
        $validated = $request->validate([
            'step' => ['required', 'in:welcome_call,platform_tour,week1_checkin'],
        ]);

        $checklist->update([
            "{$validated['step']}_at" => now(),
            'assigned_to' => $checklist->assigned_to ?? $request->user()->id,
        ]);

        return response()->json(['data' => [
            'id' => $checklist->id,
            'welcome_call_at' => $checklist->welcome_call_at?->toIso8601String(),
            'platform_tour_at' => $checklist->platform_tour_at?->toIso8601String(),
            'week1_checkin_at' => $checklist->week1_checkin_at?->toIso8601String(),
        ]]);
    }
}
