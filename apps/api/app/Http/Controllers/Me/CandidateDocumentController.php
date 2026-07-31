<?php

declare(strict_types=1);

namespace App\Http\Controllers\Me;

use App\Http\Controllers\Controller;
use App\Jobs\AssessCandidateDocument;
use App\Models\CandidateDocument;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

/**
 * Documents a candidate uploads towards their verification (PRD-E F9).
 *
 * The candidate sees their own files and whether each has been reviewed. They
 * never see the AI's assessment: it is triage written for an ops reviewer,
 * and showing a person a machine's list of "concerns" about their payslip —
 * before a human has even looked — would be both alarming and premature.
 */
final class CandidateDocumentController extends Controller
{
    /** Five megabytes, matching the CV import ceiling. */
    private const MAX_KILOBYTES = 5120;

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $documents = app(TenantContext::class)->run(
            $user->tenant,
            fn () => CandidateDocument::query()->where('user_id', $user->id)->orderByDesc('id')->get(),
        );

        return response()->json([
            'data' => $documents->map(fn (CandidateDocument $d): array => [
                'id' => $d->id,
                'kind' => $d->kind,
                'label' => $d->label(),
                'original_name' => $d->original_name,
                'size_bytes' => $d->size_bytes,
                'review_status' => $d->review_status,
                // Said plainly so a candidate can re-upload in a format we
                // can read rather than waiting on a queue that will not move.
                'machine_readable' => $d->extract_status !== CandidateDocument::EXTRACT_UNREADABLE,
                'uploaded_at' => $d->created_at?->toIso8601String(),
                'reviewed_at' => $d->reviewed_at?->toIso8601String(),
            ])->all(),
            'meta' => [
                'kinds' => collect(CandidateDocument::KINDS)
                    ->map(fn (string $label, string $key): array => ['key' => $key, 'label' => $label])
                    ->values()
                    ->all(),
                'max_kilobytes' => self::MAX_KILOBYTES,
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'kind' => ['required', Rule::in(array_keys(CandidateDocument::KINDS))],
            'file' => ['required', 'file', 'max:'.self::MAX_KILOBYTES],
        ]);

        $user = $request->user();
        $file = $request->file('file');

        $document = app(TenantContext::class)->run($user->tenant, function () use ($user, $file, $validated): CandidateDocument {
            // Private disk: these are somebody's identity papers, and nothing
            // here is ever served publicly.
            $path = $file->store("verification/{$user->id}", config('filesystems.default'));

            $document = CandidateDocument::query()->create([
                'tenant_id' => $user->tenant_id,
                'user_id' => $user->id,
                'kind' => $validated['kind'],
                'path' => $path,
                'original_name' => mb_substr((string) $file->getClientOriginalName(), 0, 255),
                'mime' => $file->getClientMimeType(),
                'size_bytes' => (int) $file->getSize(),
            ]);

            AssessCandidateDocument::dispatch($document->id);

            return $document;
        });

        return response()->json(['data' => [
            'id' => $document->id,
            'kind' => $document->kind,
            'label' => $document->label(),
            'original_name' => $document->original_name,
            'review_status' => $document->review_status,
            'uploaded_at' => $document->created_at?->toIso8601String(),
        ]], 201);
    }

    /**
     * Remove a document the candidate uploaded, unless a reviewer has already
     * ruled on it — a settled check must keep the evidence it was settled on.
     */
    public function destroy(Request $request, CandidateDocument $document): JsonResponse
    {
        $user = $request->user();
        abort_unless($document->user_id === $user->id, 404);

        abort_if(
            $document->review_status !== CandidateDocument::REVIEW_PENDING,
            422,
            'This document has already been reviewed and stays on the record.',
        );

        app(TenantContext::class)->run($user->tenant, function () use ($document): void {
            Storage::disk(config('filesystems.default'))->delete($document->path);
            $document->delete();
        });

        return response()->json(null, 204);
    }
}
