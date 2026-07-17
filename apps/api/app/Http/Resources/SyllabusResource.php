<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Syllabus;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Admin/trainer view of a course syllabus (PRD §6.2), including the draft content and
 * a short-lived signed download URL for the current rendered artifact (if any).
 *
 * @mixin Syllabus
 */
final class SyllabusResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'course_id' => $this->course_id,
            'title' => $this->title,
            'content' => $this->content,
            'status' => $this->status->value,
            'is_stale' => $this->is_stale,
            'render_status' => $this->render_status,
            'version' => $this->version,
            'downloadable' => $this->isDownloadable(),
            'approved_at' => $this->approved_at?->toIso8601String(),
            'download_url' => $this->downloadUrl(),
        ];
    }

    private function downloadUrl(): ?string
    {
        if (! $this->isDownloadable() || $this->storage_path === null) {
            return null;
        }

        try {
            return Storage::disk((string) config('syllabus.render_disk', 's3'))
                ->temporaryUrl($this->storage_path, now()->addMinutes((int) config('syllabus.download.signed_url_ttl_min', 60)));
        } catch (Throwable) {
            return null;
        }
    }
}
