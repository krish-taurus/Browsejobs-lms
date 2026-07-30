<?php

declare(strict_types=1);

namespace App\Http\Controllers\Employer;

use App\Actions\Employers\RegenerateJdMock;
use App\Http\Controllers\Controller;
use App\Http\Resources\JdMockResource;
use App\Models\EmployerJob;
use App\Models\EmployerWorkspace;
use App\Support\Employers\ResolvesMembership;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class JdMockController extends Controller
{
    use ResolvesMembership;

    public function show(Request $request, EmployerWorkspace $workspace, EmployerJob $job): JsonResponse
    {
        $this->membershipOrFail($workspace, $request->user());
        abort_unless($job->employer_workspace_id === $workspace->id, 404);

        $mock = $job->currentMock();
        abort_if($mock === null, 404, 'No mock has been generated for this JD yet.');

        return (new JdMockResource($mock))->response();
    }

    public function regenerate(Request $request, EmployerWorkspace $workspace, EmployerJob $job, RegenerateJdMock $regenerate): JsonResponse
    {
        $member = $this->membershipOrFail($workspace, $request->user());
        abort_unless($member->role->managesPipeline(), 403);
        abort_unless($job->employer_workspace_id === $workspace->id, 404);

        $mock = $regenerate->handle($job, $request->user());

        return (new JdMockResource($mock))->response()->setStatusCode(202);
    }
}
