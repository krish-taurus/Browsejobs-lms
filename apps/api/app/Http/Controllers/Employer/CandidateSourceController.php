<?php

declare(strict_types=1);

namespace App\Http\Controllers\Employer;

use App\Actions\Employers\ImportEmployerCandidates;
use App\Http\Controllers\Controller;
use App\Http\Requests\Employers\ImportEmployerCandidatesRequest;
use App\Http\Resources\EmployerCandidateImportResource;
use App\Models\EmployerCandidate;
use App\Models\EmployerCandidateImport;
use App\Models\EmployerWorkspace;
use App\Support\Audit\AuditLogger;
use App\Support\Employers\ResolvesMembership;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The client's own candidate sources for the shortlist agent: their
 * candidate database and their Naukri export, both uploaded as Excel/CSV.
 * Shared by every JD in the workspace.
 */
final class CandidateSourceController extends Controller
{
    use ResolvesMembership;

    public function index(Request $request, EmployerWorkspace $workspace): JsonResponse
    {
        $this->membershipOrFail($workspace, $request->user());

        return response()->json(['data' => $this->payload($workspace)]);
    }

    public function import(ImportEmployerCandidatesRequest $request, EmployerWorkspace $workspace, ImportEmployerCandidates $import): JsonResponse
    {
        $member = $this->membershipOrFail($workspace, $request->user());
        abort_unless($member->role->managesPipeline(), 403);

        $result = $import->handle($workspace, (string) $request->validated('source'), $request->file('file'), $request->user());

        return response()->json([
            'data' => [
                ...$this->payload($workspace),
                'import' => (new EmployerCandidateImportResource($result))->resolve(),
            ],
        ], 201);
    }

    /** Remove every candidate from one source (e.g. before uploading a fresh Naukri export). */
    public function clear(Request $request, EmployerWorkspace $workspace, string $source, AuditLogger $audit): JsonResponse
    {
        $member = $this->membershipOrFail($workspace, $request->user());
        abort_unless($member->role->managesPipeline(), 403);
        abort_unless(in_array($source, EmployerCandidate::SOURCES, true), 404);

        $deleted = EmployerCandidate::query()
            ->where('employer_workspace_id', $workspace->id)
            ->where('source', $source)
            ->delete();

        $audit->log('employer.candidates_cleared', $workspace, ['source' => $source, 'deleted' => $deleted], $request->user());

        return response()->json(['data' => $this->payload($workspace)]);
    }

    /** @return array<string, mixed> */
    private function payload(EmployerWorkspace $workspace): array
    {
        $counts = EmployerCandidate::query()
            ->where('employer_workspace_id', $workspace->id)
            ->selectRaw('source, count(*) as n')
            ->groupBy('source')
            ->pluck('n', 'source');

        $imports = EmployerCandidateImport::query()
            ->where('employer_workspace_id', $workspace->id)
            ->with('importedBy:id,name')
            ->latest('id')
            ->limit(10)
            ->get();

        return [
            'counts' => [
                EmployerCandidate::SOURCE_CLIENT_DB => (int) ($counts[EmployerCandidate::SOURCE_CLIENT_DB] ?? 0),
                EmployerCandidate::SOURCE_NAUKRI => (int) ($counts[EmployerCandidate::SOURCE_NAUKRI] ?? 0),
            ],
            'imports' => EmployerCandidateImportResource::collection($imports)->resolve(),
        ];
    }
}
