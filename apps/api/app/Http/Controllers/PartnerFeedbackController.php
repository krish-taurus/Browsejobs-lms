<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\HiringPartnerFeedback;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Public hiring-partner feedback form (PRD §6.21). No auth — companies aren't
 * platform users; the unguessable token IS the key (like a shared-CV link). The
 * candidate is never named to the company beyond the role they interviewed for.
 * Single-use: once submitted, the form is closed.
 */
final class PartnerFeedbackController extends Controller
{
    public function show(string $token): JsonResponse
    {
        $feedback = $this->resolve($token);

        return response()->json(['data' => [
            'company' => $feedback->company,
            'role_title' => $feedback->role_title,
            'submitted' => $feedback->status === HiringPartnerFeedback::STATUS_SUBMITTED,
        ]]);
    }

    public function submit(Request $request, string $token): JsonResponse
    {
        $feedback = $this->resolve($token);

        abort_if($feedback->status === HiringPartnerFeedback::STATUS_SUBMITTED, 422, 'This feedback has already been submitted.');

        $data = $request->validate([
            'ratings' => ['required', 'array'],
            'ratings.technical' => ['required', 'integer', 'between:1,5'],
            'ratings.problem_solving' => ['required', 'integer', 'between:1,5'],
            'ratings.communication' => ['required', 'integer', 'between:1,5'],
            'ratings.culture_fit' => ['required', 'integer', 'between:1,5'],
            'overall' => ['required', 'integer', 'between:1,5'],
            'would_hire' => ['required', 'boolean'],
            'strengths' => ['nullable', 'string', 'max:2000'],
            'gaps' => ['nullable', 'string', 'max:2000'],
        ]);

        $feedback->update([
            'ratings' => [
                'technical' => (int) $data['ratings']['technical'],
                'problem_solving' => (int) $data['ratings']['problem_solving'],
                'communication' => (int) $data['ratings']['communication'],
                'culture_fit' => (int) $data['ratings']['culture_fit'],
            ],
            'overall' => (int) $data['overall'],
            'would_hire' => (bool) $data['would_hire'],
            'strengths' => $data['strengths'] ?? null,
            'gaps' => $data['gaps'] ?? null,
            'status' => HiringPartnerFeedback::STATUS_SUBMITTED,
            'submitted_at' => now(),
        ]);

        return response()->json(['data' => ['submitted' => true]]);
    }

    /** Token is globally unique; resolve across tenants like the shared-CV link. */
    private function resolve(string $token): HiringPartnerFeedback
    {
        return HiringPartnerFeedback::query()
            ->withoutGlobalScopes()
            ->where('token', $token)
            ->firstOrFail();
    }
}
