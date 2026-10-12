<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\EmployerCandidateImport;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin EmployerCandidateImport */
final class EmployerCandidateImportResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'source' => $this->source,
            'file_name' => $this->original_name,
            'rows_total' => $this->rows_total,
            'created' => $this->created_count,
            'updated' => $this->updated_count,
            'skipped' => $this->skipped_count,
            'errors' => $this->errors ?? [],
            'imported_by' => $this->importedBy?->name,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
