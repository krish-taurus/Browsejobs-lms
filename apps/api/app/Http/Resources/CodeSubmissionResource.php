<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\CodeSubmission;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin CodeSubmission
 */
final class CodeSubmissionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'kind' => $this->kind->value,
            'status' => $this->status,
            'stdout' => $this->stdout,
            'stderr' => $this->stderr,
            'passed_tests' => $this->passed_tests,
            'total_tests' => $this->total_tests,
            'run_time_ms' => $this->run_time_ms,
            'memory_kb' => $this->memory_kb,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
