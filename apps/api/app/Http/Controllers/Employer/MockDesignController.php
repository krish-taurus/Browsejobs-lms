<?php

declare(strict_types=1);

namespace App\Http\Controllers\Employer;

use App\Http\Controllers\Controller;
use App\Models\EmployerJob;
use App\Models\EmployerWorkspace;
use App\Support\Audit\AuditLogger;
use App\Support\Employers\MockDesign;
use App\Support\Employers\ResolvesMembership;
use App\Support\Roles\RoleTaxonomy;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The interview designer for one JD (PRD-E F16).
 *
 * Saving a design does not regenerate the mock — an employer tuning weights
 * should not silently invalidate questions candidates are mid-way through.
 * The response says whether a regenerate is needed and they choose.
 */
final class MockDesignController extends Controller
{
    use ResolvesMembership;

    public function show(Request $request, EmployerWorkspace $workspace, EmployerJob $job, RoleTaxonomy $taxonomy): JsonResponse
    {
        $this->membershipOrFail($workspace, $request->user());
        abort_unless($job->employer_workspace_id === $workspace->id, 404);

        $design = MockDesign::fromArray($job->mock_design, $taxonomy);
        $suggested = $taxonomy->skillsForTitle($job->title);

        return response()->json(['data' => [
            'design' => $design->toArray(),
            'normalised' => [
                'competencies' => $design->normalisedCompetencies(),
                'formats' => $design->normalisedFormats(),
            ],
            'competencies' => $taxonomy->competencies(),
            'question_formats' => $taxonomy->questionFormats(),
            // The JD's own skills first, then the family's, so the picker
            // offers what this role actually names before anything generic.
            'selectable_skills' => array_values(array_unique([
                ...array_map('mb_strtolower', $job->skills ?? []),
                ...$suggested['core'],
                ...$suggested['optional'],
            ])),
            'has_mock' => $job->currentMock() !== null,
        ]]);
    }

    public function update(
        Request $request,
        EmployerWorkspace $workspace,
        EmployerJob $job,
        RoleTaxonomy $taxonomy,
        AuditLogger $audit,
    ): JsonResponse {
        $member = $this->membershipOrFail($workspace, $request->user());
        abort_unless($member->role->managesPipeline(), 403);
        abort_unless($job->employer_workspace_id === $workspace->id, 404);

        $validated = $request->validate([
            'focus_skills' => ['nullable', 'array', 'max:12'],
            'focus_skills.*' => ['string', 'max:60'],
            'competency_weights' => ['nullable', 'array'],
            'competency_weights.*' => ['integer', 'min:0', 'max:100'],
            'format_mix' => ['nullable', 'array'],
            'format_mix.*' => ['integer', 'min:0', 'max:100'],
            'question_count' => ['nullable', 'integer', 'min:4', 'max:20'],
            'notes' => ['nullable', 'string', 'max:800'],
        ]);

        $design = MockDesign::fromArray($validated, $taxonomy);

        $job->forceFill(['mock_design' => $design->isEmpty() ? null : $design->toArray()])->save();

        $audit->log('employer.mock_design_updated', $job, [
            'focus_skills' => $design->focusSkills,
            'competencies' => $design->normalisedCompetencies(),
        ], $request->user());

        return response()->json(['data' => [
            'design' => $design->toArray(),
            'normalised' => [
                'competencies' => $design->normalisedCompetencies(),
                'formats' => $design->normalisedFormats(),
            ],
            // Saving never regenerates: candidates may be mid-interview.
            'regenerate_required' => $job->currentMock() !== null,
        ]]);
    }
}
