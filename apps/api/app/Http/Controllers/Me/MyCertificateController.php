<?php

declare(strict_types=1);

namespace App\Http\Controllers\Me;

use App\Http\Controllers\Controller;
use App\Models\Certificate;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * The student's certificates (PRD §6.5). Under auth:sanctum without tenant.user, so it
 * wraps in the student's tenant context. The rendered file is private — served via a
 * 1h signed URL, never publicly.
 */
final class MyCertificateController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        return app(TenantContext::class)->run($request->user()->tenant, function () use ($request): JsonResponse {
            $certificates = Certificate::query()
                ->where('user_id', $request->user()->id)
                ->with('course:id,name')
                ->orderByDesc('issued_at')
                ->get();

            $frontend = rtrim((string) config('app.frontend_url'), '/');

            return response()->json(['data' => $certificates->map(fn (Certificate $c) => [
                'code' => $c->code,
                'number' => $c->number,
                'title' => $c->title,
                'course' => $c->course?->name,
                'issued_on' => $c->issued_at?->toDateString(),
                'status' => $c->status,
                'revoked' => $c->isRevoked(),
                'verify_url' => $frontend.'/verify/'.$c->code,
                'download' => $this->download($c),
            ])->all()]);
        });
    }

    private function download(Certificate $certificate): ?string
    {
        if ($certificate->storage_path === null || $certificate->status !== Certificate::STATUS_RENDERED) {
            return null;
        }

        try {
            return Storage::disk('s3')->temporaryUrl($certificate->storage_path, now()->addHour());
        } catch (Throwable) {
            return null;
        }
    }
}
