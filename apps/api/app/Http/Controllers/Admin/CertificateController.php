<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Actions\Certificates\IssueCertificate;
use App\Actions\Certificates\RevokeCertificate;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\IssueCertificateRequest;
use App\Http\Resources\CertificateResource;
use App\Models\Certificate;
use App\Models\Course;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Admin/trainer certificate management (PRD §6.5). Gated by `can:manage-rosters`.
 * Certificates auto-issue on course completion; this is the manual issue + revoke path.
 */
final class CertificateController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $certificates = Certificate::query()
            ->with(['student:id,name', 'course:id,name'])
            ->when($request->filled('course_id'), fn ($q) => $q->where('course_id', $request->integer('course_id')))
            ->when($request->string('status')->toString() === 'revoked', fn ($q) => $q->whereNotNull('revoked_at'))
            ->when($request->string('status')->toString() === 'active', fn ($q) => $q->whereNull('revoked_at'))
            ->orderByDesc('id')
            ->limit(200)
            ->get();

        return CertificateResource::collection($certificates)->response();
    }

    public function store(IssueCertificateRequest $request, IssueCertificate $issue): JsonResponse
    {
        $student = User::query()->findOrFail($request->integer('user_id'));
        $course = Course::query()->findOrFail($request->integer('course_id'));

        $certificate = $issue->handle($student, $course, $request->user());

        return (new CertificateResource($certificate->load(['student:id,name', 'course:id,name'])))
            ->response()->setStatusCode(201);
    }

    public function revoke(Request $request, Certificate $certificate, RevokeCertificate $revoke): JsonResponse
    {
        $revoke->handle($certificate, $request->user());

        return (new CertificateResource($certificate->refresh()->load(['student:id,name', 'course:id,name'])))->response();
    }
}
