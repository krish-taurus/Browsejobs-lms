<?php

declare(strict_types=1);

namespace App\Http\Controllers\Taurus;

use App\Actions\Taurus\ClearWorkspaceCredential;
use App\Actions\Taurus\RotateIngestToken;
use App\Actions\Taurus\SaveWorkspaceBrain;
use App\Actions\Taurus\TestBrainConnection;
use App\Http\Controllers\Controller;
use App\Http\Requests\Taurus\TestBrainRequest;
use App\Http\Requests\Taurus\UpdateWorkspaceBrainRequest;
use App\Models\TaurusWorkspace;
use App\Models\TaurusWorkspaceCredential;
use App\Support\Taurus\BrainStatus;
use App\Support\Taurus\WorkspaceBrainStatus;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * A workspace's "Brain & voice" (ADR 0052): its own LLM and ElevenLabs keys
 * and its bot ingest token. Owner/admin only (`taurus.member:manage`). No
 * response ever carries a key; the ingest token is shown once, on rotation.
 */
final class WorkspaceBrainController extends Controller
{
    public function show(TaurusWorkspace $workspace, WorkspaceBrainStatus $status): JsonResponse
    {
        return response()->json(['data' => $status->toArray($workspace)]);
    }

    public function update(UpdateWorkspaceBrainRequest $request, TaurusWorkspace $workspace, SaveWorkspaceBrain $save, WorkspaceBrainStatus $status): JsonResponse
    {
        $save->handle($workspace, $request->validated(), $request->user());

        return response()->json(['data' => $status->toArray($workspace->refresh())]);
    }

    public function clearProvider(Request $request, TaurusWorkspace $workspace, string $provider, ClearWorkspaceCredential $clear, WorkspaceBrainStatus $status): JsonResponse
    {
        if ($provider !== TaurusWorkspaceCredential::ELEVENLABS && ! in_array($provider, BrainStatus::PROVIDERS, true)) {
            abort(404);
        }

        $clear->handle($workspace, $provider, $request->user());

        return response()->json(['data' => $status->toArray($workspace)]);
    }

    public function test(TestBrainRequest $request, TaurusWorkspace $workspace, TestBrainConnection $test): JsonResponse
    {
        return response()->json(['data' => $test->forWorkspace($workspace, (string) $request->validated('target'))]);
    }

    /** The only response that ever carries the token — show it once, then it's masked. */
    public function rotateIngestToken(Request $request, TaurusWorkspace $workspace, RotateIngestToken $rotate): JsonResponse
    {
        $token = $rotate->handle($workspace, $request->user());

        return response()->json(['data' => [
            'token' => $token,
            'endpoint' => BrainStatus::ingestEndpoint(),
        ]]);
    }
}
