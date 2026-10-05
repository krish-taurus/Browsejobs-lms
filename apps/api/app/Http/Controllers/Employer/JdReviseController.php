<?php

declare(strict_types=1);

namespace App\Http\Controllers\Employer;

use App\Actions\Employers\ReviseJobDescription;
use App\Http\Controllers\Controller;
use App\Models\EmployerWorkspace;
use App\Support\Employers\ResolvesMembership;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Apply one spoken change to a draft JD. Creates and publishes nothing — the
 * draft only exists in the employer's browser until they post it.
 */
final class JdReviseController extends Controller
{
    use ResolvesMembership;

    public function __invoke(Request $request, EmployerWorkspace $workspace, ReviseJobDescription $revise): JsonResponse
    {
        $member = $this->membershipOrFail($workspace, $request->user());
        abort_unless($member->role->managesPipeline(), 403);

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:160'],
            'description' => ['required', 'string', 'max:8000'],
            'skills' => ['array', 'max:30'],
            'skills.*' => ['string', 'max:40'],
            'instruction' => ['required', 'string', 'max:600'],
        ]);

        return response()->json([
            'data' => $revise->handle(
                $request->user(),
                $validated['title'],
                $validated['description'],
                $validated['skills'] ?? [],
                $validated['instruction'],
            ),
        ]);
    }
}
