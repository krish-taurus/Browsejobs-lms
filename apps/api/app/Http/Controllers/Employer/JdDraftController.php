<?php

declare(strict_types=1);

namespace App\Http\Controllers\Employer;

use App\Actions\Employers\DraftJobDescription;
use App\Http\Controllers\Controller;
use App\Models\EmployerWorkspace;
use App\Support\Employers\ResolvesMembership;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Draft a JD from a title (PRD-E F15). Returns a draft for the employer to
 * edit — it never creates or publishes anything.
 */
final class JdDraftController extends Controller
{
    use ResolvesMembership;

    public function store(Request $request, EmployerWorkspace $workspace, DraftJobDescription $draft): JsonResponse
    {
        $member = $this->membershipOrFail($workspace, $request->user());
        abort_unless($member->role->managesPipeline(), 403);

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:160'],
            'notes' => ['nullable', 'string', 'max:1500'],
            'experience_min_years' => ['nullable', 'integer', 'min:0', 'max:40'],
            'experience_max_years' => ['nullable', 'integer', 'min:0', 'max:40'],
            'locations' => ['nullable', 'array', 'max:10'],
            'locations.*' => ['string', 'max:120'],
        ]);

        return response()->json([
            'data' => $draft->handle($request->user(), $workspace, $validated['title'], $validated),
        ]);
    }
}
