<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Actions\Tutor\IngestKnowledgeDocument;
use App\Actions\Tutor\RebuildTutorIndex;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\KnowledgeDocumentRequest;
use App\Http\Resources\KnowledgeDocumentResource;
use App\Models\KnowledgeDocument;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Admin/trainer authoring of the AI Tutor knowledge base (PRD §6.4). Gated by
 * `can:manage-curriculum`. Ingested content (`source_type` course/program/coding_lab)
 * is read-only here — only `manual` docs are editable; both are searchable.
 */
final class KnowledgeController extends Controller
{
    public function __construct(private readonly IngestKnowledgeDocument $ingest) {}

    public function index(Request $request): JsonResponse
    {
        $documents = KnowledgeDocument::query()
            ->withCount('chunks')
            ->orderByDesc('id')
            ->get();

        return KnowledgeDocumentResource::collection($documents)->response();
    }

    public function store(KnowledgeDocumentRequest $request): JsonResponse
    {
        $document = KnowledgeDocument::query()->create([
            'tenant_id' => $request->user()->tenant_id,
            'title' => $request->string('title')->toString(),
            'source_type' => 'manual',
            'body' => $request->string('body')->toString(),
            'course_id' => $request->integer('course_id') ?: null,
            'lesson_id' => $request->integer('lesson_id') ?: null,
            'is_active' => $request->boolean('is_active', true),
            'authored_by' => $request->user()->id,
        ]);

        $this->ingest->handle($document);

        return (new KnowledgeDocumentResource($document->loadCount('chunks')))->response()->setStatusCode(201);
    }

    public function update(KnowledgeDocumentRequest $request, KnowledgeDocument $knowledge): JsonResponse
    {
        abort_unless($knowledge->source_type === 'manual', 422, 'Only authored documents can be edited.');

        $knowledge->update([
            'title' => $request->string('title')->toString(),
            'body' => $request->string('body')->toString(),
            'course_id' => $request->integer('course_id') ?: null,
            'lesson_id' => $request->integer('lesson_id') ?: null,
            'is_active' => $request->boolean('is_active', true),
        ]);

        $this->ingest->handle($knowledge);

        return (new KnowledgeDocumentResource($knowledge->loadCount('chunks')))->response();
    }

    public function destroy(KnowledgeDocument $knowledge): JsonResponse
    {
        abort_unless($knowledge->source_type === 'manual', 422, 'Only authored documents can be deleted.');

        $knowledge->delete();

        return response()->json(status: 204);
    }

    public function reindex(RebuildTutorIndex $action): JsonResponse
    {
        $summary = $action->handle(request()->user()->tenant);

        return response()->json(['data' => $summary]);
    }
}
