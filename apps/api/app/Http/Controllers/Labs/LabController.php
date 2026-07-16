<?php

declare(strict_types=1);

namespace App\Http\Controllers\Labs;

use App\Actions\Labs\RunCode;
use App\Enums\SubmissionKind;
use App\Http\Controllers\Controller;
use App\Http\Requests\Labs\RunCodeRequest;
use App\Http\Resources\CodeSubmissionResource;
use App\Http\Resources\CodingLabResource;
use App\Models\CodingLab;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Student coding labs (PRD §6.5). Under auth:sanctum without tenant.user, so work
 * is wrapped in the student's tenant context; expected test outputs are never sent.
 */
final class LabController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        return app(TenantContext::class)->run($request->user()->tenant, function (): JsonResponse {
            $labs = CodingLab::query()->with('lesson:id,title')->orderBy('id')->get();

            return response()->json(['data' => $labs->map(fn (CodingLab $l) => [
                'lesson_id' => $l->lesson_id,
                'title' => $l->lesson?->title,
                'language' => $l->language->value,
            ])->all()]);
        });
    }

    public function show(Request $request, int $lesson): JsonResponse
    {
        $lab = $this->lab($request, $lesson);

        return (new CodingLabResource($lab))->response();
    }

    public function run(RunCodeRequest $request, int $lesson, RunCode $run): JsonResponse
    {
        $lab = $this->lab($request, $lesson);
        $submission = $run->handle($request->user(), $lab, $request->string('source')->toString(), SubmissionKind::Run);

        return (new CodeSubmissionResource($submission))->response();
    }

    public function submit(RunCodeRequest $request, int $lesson, RunCode $run): JsonResponse
    {
        $lab = $this->lab($request, $lesson);
        $submission = $run->handle($request->user(), $lab, $request->string('source')->toString(), SubmissionKind::Submit);

        return (new CodeSubmissionResource($submission))->response();
    }

    private function lab(Request $request, int $lessonId): CodingLab
    {
        return app(TenantContext::class)->run(
            $request->user()->tenant,
            fn (): CodingLab => CodingLab::query()->with('lesson:id,title')->where('lesson_id', $lessonId)->firstOrFail(),
        );
    }
}
