<?php

declare(strict_types=1);

namespace App\Http\Controllers\Employer;

use App\Actions\Employers\BuildRecruiterFloor;
use App\Http\Controllers\Controller;
use App\Models\EmployerWorkspace;
use App\Support\Employers\ResolvesMembership;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The live AI Recruiter floor for a workspace (optionally one JD): the
 * hiring agents, the people in each step and what just happened. Read-only;
 * the page polls it.
 */
final class RecruiterFloorController extends Controller
{
    use ResolvesMembership;

    public function show(Request $request, EmployerWorkspace $workspace, BuildRecruiterFloor $floor): JsonResponse
    {
        $this->membershipOrFail($workspace, $request->user());
        $request->validate(['job' => ['nullable', 'integer']]);

        return response()->json(['data' => $floor->handle($workspace, $request->filled('job') ? $request->integer('job') : null)]);
    }
}
