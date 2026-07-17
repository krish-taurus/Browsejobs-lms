<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Certificate;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Admin view of a certificate (PRD §6.5). Exposes a signed download for rendered certs;
 * never the raw storage path.
 *
 * @mixin Certificate
 */
final class CertificateResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $frontend = rtrim((string) config('app.frontend_url'), '/');

        return [
            'id' => $this->id,
            'code' => $this->code,
            'number' => $this->number,
            'title' => $this->title,
            'student' => $this->whenLoaded('student', fn () => $this->student?->name),
            'course' => $this->whenLoaded('course', fn () => $this->course?->name),
            'issued_on' => $this->issued_at?->toDateString(),
            'status' => $this->status,
            'revoked' => $this->isRevoked(),
            'revoked_at' => $this->revoked_at?->toIso8601String(),
            'verify_url' => $frontend.'/verify/'.$this->code,
            'download' => $this->signedDownload(),
        ];
    }

    private function signedDownload(): ?string
    {
        if ($this->storage_path === null || $this->status !== Certificate::STATUS_RENDERED) {
            return null;
        }

        try {
            return Storage::disk('s3')->temporaryUrl($this->storage_path, now()->addHour());
        } catch (Throwable) {
            return null;
        }
    }
}
