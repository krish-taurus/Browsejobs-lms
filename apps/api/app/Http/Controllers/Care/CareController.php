<?php

declare(strict_types=1);

namespace App\Http\Controllers\Care;

use App\Enums\BatchMemberStatus;
use App\Http\Controllers\Controller;
use App\Models\BatchMember;
use App\Models\EnrolmentPause;
use App\Models\InAppNotification;
use App\Models\NpsPulse;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * Student check-in surface (PRD §6.20): pending NPS pulses (promoters get
 * the unconditioned Google-review invitation, detractors trigger instant
 * counselor rescue) and the pause/defer request flow.
 */
final class CareController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        return app(TenantContext::class)->run($request->user()->tenant, function () use ($request): JsonResponse {
            $pulses = NpsPulse::query()
                ->where('user_id', $request->user()->id)
                ->orderByDesc('id')
                ->get();

            $pauses = EnrolmentPause::query()
                ->where('user_id', $request->user()->id)
                ->orderByDesc('id')
                ->get();

            return response()->json(['data' => [
                'pending_pulses' => $pulses->whereNull('score')->values()->map(fn (NpsPulse $p) => [
                    'id' => $p->id, 'milestone' => $p->milestone,
                ]),
                'answered' => $pulses->whereNotNull('score')->values()->map(fn (NpsPulse $p) => [
                    'milestone' => $p->milestone, 'score' => $p->score,
                ]),
                'pauses' => $pauses->map(fn (EnrolmentPause $p) => [
                    'id' => $p->id,
                    'status' => $p->status,
                    'reason' => $p->reason,
                    'paused_until' => $p->paused_until?->toDateString(),
                ]),
                'can_request_pause' => $pauses->whereIn('status', ['requested', 'approved'])->isEmpty(),
            ]]);
        });
    }

    public function respond(Request $request, int $pulse): JsonResponse
    {
        $validated = $request->validate([
            'score' => ['required', 'integer', 'min:0', 'max:10'],
            'comment' => ['nullable', 'string', 'max:1000'],
        ]);

        return app(TenantContext::class)->run($request->user()->tenant, function () use ($request, $pulse, $validated): JsonResponse {
            $model = NpsPulse::query()
                ->where('user_id', $request->user()->id)
                ->whereNull('score')
                ->findOrFail($pulse);

            $score = (int) $validated['score'];
            $googleUrl = (string) config('retention.nps.google_review_url', '');

            $routed = match (true) {
                $score >= (int) config('retention.nps.promoter_min', 9) && $googleUrl !== '' => 'google_review',
                $score <= (int) config('retention.nps.detractor_max', 6) => 'rescue',
                default => 'none',
            };

            $model->update([
                'score' => $score,
                'comment' => $validated['comment'] ?? null,
                'routed' => $routed,
                'responded_at' => now(),
            ]);

            if ($routed === 'rescue') {
                $this->rescue($request->user(), $model);
            }

            return response()->json(['data' => [
                'routed' => $routed,
                // Unconditioned invitation (policy-safe): shown only to
                // promoters, never tied to any reward.
                'review_url' => $routed === 'google_review' ? $googleUrl : null,
            ]]);
        });
    }

    public function requestPause(Request $request): JsonResponse
    {
        $validated = $request->validate(['reason' => ['required', 'string', 'min:10', 'max:1000']]);

        return app(TenantContext::class)->run($request->user()->tenant, function () use ($request, $validated): JsonResponse {
            $student = $request->user();

            $member = BatchMember::query()
                ->where('user_id', $student->id)
                ->where('status', BatchMemberStatus::Enrolled->value)
                ->latest('id')
                ->first();

            if ($member === null) {
                throw ValidationException::withMessages(['pause' => 'Only active enrolments can be paused.']);
            }

            $open = EnrolmentPause::query()
                ->where('user_id', $student->id)
                ->whereIn('status', [EnrolmentPause::STATUS_REQUESTED, EnrolmentPause::STATUS_APPROVED])
                ->exists();

            if ($open) {
                throw ValidationException::withMessages(['pause' => 'You already have an active pause request.']);
            }

            $pause = EnrolmentPause::query()->create([
                'tenant_id' => $student->tenant_id,
                'user_id' => $student->id,
                'batch_member_id' => $member->id,
                'reason' => $validated['reason'],
            ]);

            foreach ($this->counselors($student->tenant) as $counselor) {
                InAppNotification::query()->create([
                    'tenant_id' => $student->tenant_id,
                    'user_id' => $counselor->id,
                    'title' => "Pause request: {$student->name}",
                    'body' => str($validated['reason'])->limit(120)->toString(),
                    'url' => '/admin/care',
                ]);
            }

            return response()->json(['data' => ['id' => $pause->id, 'status' => $pause->status]], 201);
        });
    }

    private function rescue(User $student, NpsPulse $pulse): void
    {
        foreach ($this->counselors($student->tenant) as $counselor) {
            InAppNotification::query()->create([
                'tenant_id' => $student->tenant_id,
                'user_id' => $counselor->id,
                'title' => "Detractor rescue: {$student->name} scored {$pulse->score}",
                'body' => ($pulse->comment !== null ? str($pulse->comment)->limit(120).' — ' : '')
                    .'Call today, before the frustration goes anywhere public.',
                'url' => '/admin/care',
            ]);
        }
    }

    /**
     * @return Collection<int, User>
     */
    private function counselors(?Tenant $tenant)
    {
        return User::query()
            ->where('tenant_id', $tenant?->id)
            ->where('user_type', 'staff')
            ->whereHas('roles', fn ($q) => $q->where('slug', 'counselor'))
            ->get();
    }
}
