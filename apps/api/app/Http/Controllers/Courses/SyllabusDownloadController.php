<?php

declare(strict_types=1);

namespace App\Http\Controllers\Courses;

use App\Actions\Crm\CaptureLead;
use App\Http\Controllers\Controller;
use App\Http\Requests\Syllabus\DownloadSyllabusRequest;
use App\Models\Course;
use App\Models\Syllabus;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Public, lead-gated syllabus download (PRD §6.1/§6.2 lead magnet). Tenant resolved
 * from host. Only an approved + rendered syllabus is served — a draft or absent one
 * 404s, and no lead is captured in that case. On success it captures a `syllabus` lead
 * (CRM pipeline + welcome message) and returns a short-lived signed download URL.
 */
final class SyllabusDownloadController extends Controller
{
    public function store(DownloadSyllabusRequest $request, CaptureLead $capture, string $slug): JsonResponse
    {
        $tenant = app(TenantContext::class)->get();

        $course = Course::query()->where('slug', $slug)->firstOrFail();
        $syllabus = Syllabus::query()->where('course_id', $course->id)->first();

        abort_unless($syllabus !== null && $syllabus->isDownloadable(), 404);

        $capture->handle($tenant, [
            'lead_type' => 'syllabus',
            'name' => $request->string('name')->toString(),
            'phone' => $request->string('phone')->toString(),
            'email' => $request->input('email'),
            'course_slug' => $slug,
            'utm_source' => $request->input('utm_source'),
            'utm_medium' => $request->input('utm_medium'),
            'utm_campaign' => $request->input('utm_campaign'),
            'page' => 'syllabus_download',
            'ip' => $request->ip(),
            'consented_at' => now(),
            'consent_version' => 'v1',
        ]);

        return response()->json(['data' => ['download' => $this->downloadUrl($syllabus)]]);
    }

    private function downloadUrl(Syllabus $syllabus): ?string
    {
        try {
            return Storage::disk((string) config('syllabus.render_disk', 's3'))
                ->temporaryUrl($syllabus->storage_path, now()->addMinutes((int) config('syllabus.download.signed_url_ttl_min', 60)));
        } catch (Throwable) {
            return null;
        }
    }
}
