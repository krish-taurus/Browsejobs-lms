<?php

declare(strict_types=1);

namespace App\Http\Controllers\Me;

use App\Actions\Projects\RenderProjectPdf;
use App\Http\Controllers\Controller;
use App\Models\ProjectSpec;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/**
 * The capstone a student sees for a `project` lesson: the brief, the tech stack,
 * the architecture (structured, rendered live), and a short-lived signed link to
 * download the architecture file.
 */
final class MyProjectController extends Controller
{
    public function show(Request $request, int $lesson): JsonResponse
    {
        return app(TenantContext::class)->run($request->user()->tenant, function () use ($lesson): JsonResponse {
            $spec = ProjectSpec::query()->with('lesson:id,title')->where('lesson_id', $lesson)->firstOrFail();

            $download = $spec->architecture_path !== null
                ? Storage::disk(RenderProjectPdf::DISK)->temporaryUrl($spec->architecture_path, now()->addMinutes(15))
                : null;

            return response()->json(['data' => [
                'lesson_id' => $spec->lesson_id,
                'title' => $spec->title ?: $spec->lesson?->title,
                'summary' => $spec->summary,
                'tech_stack' => $spec->tech_stack ?? [],
                'architecture' => $spec->architecture,
                'download_url' => $download,
            ]]);
        });
    }
}
