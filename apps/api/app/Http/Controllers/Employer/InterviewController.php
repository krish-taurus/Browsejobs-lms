<?php

declare(strict_types=1);

namespace App\Http\Controllers\Employer;

use App\Http\Controllers\Controller;
use App\Http\Resources\EmployerInterviewResource;
use App\Models\EmployerInterview;
use App\Models\EmployerJob;
use App\Models\EmployerJobApplication;
use App\Models\EmployerWorkspace;
use App\Support\Employers\ResolvesMembership;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class InterviewController extends Controller
{
    use ResolvesMembership;

    public function index(Request $request, EmployerWorkspace $workspace, EmployerJob $job, EmployerJobApplication $application): JsonResponse
    {
        $this->membershipOrFail($workspace, $request->user());
        abort_unless($job->employer_workspace_id === $workspace->id, 404);
        abort_unless($application->employer_job_id === $job->id, 404);

        $interviews = EmployerInterview::query()
            ->where('employer_job_application_id', $application->id)
            ->orderBy('invited_at')
            ->get();

        return EmployerInterviewResource::collection($interviews)->response();
    }
}
