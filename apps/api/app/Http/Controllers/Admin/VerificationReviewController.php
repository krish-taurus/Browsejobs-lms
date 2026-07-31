<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Enums\VerificationKind;
use App\Enums\VerificationStatus;
use App\Http\Controllers\Controller;
use App\Models\CandidateDocument;
use App\Models\CandidateVerificationCheck;
use App\Support\Verification\VerificationGateway;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * The ops queue behind the verified badge (PRD-E F9).
 *
 * The manual provider deliberately never returns `verified` on its own — a
 * person decides here. That is the whole value of the badge, so this queue
 * is not optional plumbing: without it nothing a candidate submits can ever
 * be settled.
 *
 * Every decision is audited by the gateway, because a verified badge is a
 * claim BrowseJobs makes to an employer on a candidate's behalf.
 */
final class VerificationReviewController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['nullable', Rule::in(array_column(VerificationStatus::cases(), 'value'))],
            'kind' => ['nullable', Rule::in(array_column(VerificationKind::cases(), 'value'))],
        ]);

        $checks = CandidateVerificationCheck::query()
            // Pending first: the queue exists to clear a backlog, and a
            // candidate waiting on us is the only urgent state here.
            ->orderByRaw("CASE WHEN status = 'pending' THEN 0 ELSE 1 END")
            ->orderBy('submitted_at')
            ->when(
                $validated['status'] ?? null,
                fn ($q, $status) => $q->where('status', $status),
                fn ($q) => $q->whereIn('status', [
                    VerificationStatus::Pending->value,
                    VerificationStatus::Failed->value,
                ]),
            )
            ->when($validated['kind'] ?? null, fn ($q, $kind) => $q->where('kind', $kind))
            ->with('candidate:id,name,email')
            ->paginate(25);

        return response()->json([
            'data' => $checks->getCollection()->map(fn (CandidateVerificationCheck $c): array => [
                'id' => $c->id,
                'kind' => $c->kind->value,
                'kind_label' => $c->kind->label(),
                'status' => $c->effectiveStatus()->value,
                'status_label' => $c->effectiveStatus()->label(),
                'provider' => $c->provider,
                'provider_ref' => $c->provider_ref,
                'evidence' => $c->evidence,
                'failure_reason' => $c->failure_reason,
                'submitted_at' => $c->submitted_at?->toIso8601String(),
                'waiting_days' => $c->submitted_at?->diffInDays(now()),
                'candidate' => [
                    'id' => $c->candidate?->id,
                    'name' => $c->candidate?->name,
                    'email' => $c->candidate?->email,
                ],
                // Per-document triage for the documents check, so a reviewer
                // opens four files knowing which one to start with.
                'documents' => $c->kind === VerificationKind::Documents
                    ? $this->documentsFor($c->user_id)
                    : [],
            ])->all(),
            'meta' => [
                'total' => $checks->total(),
                'current_page' => $checks->currentPage(),
                'last_page' => $checks->lastPage(),
                'pending' => CandidateVerificationCheck::query()
                    ->where('status', VerificationStatus::Pending->value)
                    ->count(),
            ],
        ]);
    }

    /**
     * What the AI read in each uploaded document.
     *
     * This is advice, and the payload says so: `is_advice_only` rides along
     * with every verdict, and an unreadable file reports that rather than
     * going quiet — silence would let "no concerns" be read as "checked and
     * clean" when nothing was read at all.
     *
     * @return list<array<string, mixed>>
     */
    private function documentsFor(int $userId): array
    {
        return CandidateDocument::query()
            ->where('user_id', $userId)
            ->orderBy('id')
            ->get()
            ->map(fn (CandidateDocument $d): array => [
                'id' => $d->id,
                'kind' => $d->kind,
                'label' => $d->label(),
                'original_name' => $d->original_name,
                'machine_readable' => $d->extract_status !== CandidateDocument::EXTRACT_UNREADABLE,
                'assessed' => $d->assessed_at !== null,
                'assessment_summary' => $d->assessmentSummary(),
                'verdict' => $d->ai_verdict,
                'review_status' => $d->review_status,
                'uploaded_at' => $d->created_at?->toIso8601String(),
            ])
            ->all();
    }

    public function settle(
        Request $request,
        CandidateVerificationCheck $check,
        VerificationGateway $gateway,
    ): JsonResponse {
        $validated = $request->validate([
            'decision' => ['required', Rule::in(['verified', 'failed'])],
            // A rejection a candidate cannot act on is worse than none: they
            // will resubmit the same thing and wait again.
            'reason' => ['required_if:decision,failed', 'nullable', 'string', 'max:500'],
            'evidence' => ['nullable', 'array'],
            'evidence.issuer' => ['nullable', 'string', 'max:160'],
            'evidence.period' => ['nullable', 'string', 'max:120'],
        ]);

        $settled = $gateway->settle(
            $check,
            $validated['decision'] === 'verified' ? VerificationStatus::Verified : VerificationStatus::Failed,
            $request->user(),
            $validated['evidence'] ?? [],
            $validated['reason'] ?? null,
        );

        return response()->json(['data' => [
            'id' => $settled->id,
            'status' => $settled->status->value,
            'status_label' => $settled->status->label(),
            'verified_at' => $settled->verified_at?->toIso8601String(),
            'expires_at' => $settled->expires_at?->toIso8601String(),
        ]]);
    }
}
