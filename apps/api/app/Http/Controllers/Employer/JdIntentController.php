<?php

declare(strict_types=1);

namespace App\Http\Controllers\Employer;

use App\Actions\Employers\ReadHiringIntent;
use App\Http\Controllers\Controller;
use App\Models\EmployerWorkspace;
use App\Support\Employers\ResolvesMembership;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Read a spoken or typed hiring request into fields.
 *
 * Nothing is created here — this only decides what the console understood, and
 * therefore what it still needs to ask about.
 */
final class JdIntentController extends Controller
{
    use ResolvesMembership;

    public function __invoke(Request $request, EmployerWorkspace $workspace, ReadHiringIntent $read): JsonResponse
    {
        $member = $this->membershipOrFail($workspace, $request->user());
        abort_unless($member->role->managesPipeline(), 403);

        $validated = $request->validate([
            'said' => ['required', 'string', 'max:1200'],
        ]);

        return response()->json(['data' => $read->handle($request->user(), $validated['said'])]);
    }
}
