<?php

declare(strict_types=1);

namespace App\Http\Controllers\Employer;

use App\Http\Controllers\Controller;
use App\Models\EmployerJob;
use App\Models\EmployerJobApplication;
use App\Models\EmployerWorkspace;
use App\Support\Employers\CandidateProfile;
use App\Support\Employers\ResolvesMembership;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The full candidate view for one application (PRD-E F17).
 */
final class CandidateProfileController extends Controller
{
    use ResolvesMembership;

    public function show(
        Request $request,
        EmployerWorkspace $workspace,
        EmployerJob $job,
        EmployerJobApplication $application,
    ): JsonResponse {
        $this->membershipOrFail($workspace, $request->user());

        abort_unless($job->employer_workspace_id === $workspace->id, 404);
        abort_unless($application->employer_job_id === $job->id, 404);

        return response()->json(['data' => (new CandidateProfile($application))->build()]);
    }
}
