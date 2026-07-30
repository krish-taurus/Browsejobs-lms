<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\JdMock;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin JdMock */
final class JdMockResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'job_id' => $this->employer_job_id,
            'version' => $this->version,
            'status' => $this->status->value,
            'source' => $this->source,
            'questions' => $this->questions,
            'rubric' => $this->rubric,
            'generated_at' => $this->generated_at?->toIso8601String(),
        ];
    }
}
