<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\EmployerWorkspace;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin EmployerWorkspace */
final class EmployerWorkspaceResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $viewer = $request->user();

        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'website' => $this->website,
            'industry' => $this->industry,
            'company_size' => $this->company_size,
            'gstin' => $this->gstin,
            'locations' => $this->locations,
            'status' => $this->status,
            'my_role' => $viewer instanceof User ? $this->roleFor($viewer)?->value : null,
            'members_count' => $this->whenCounted('members'),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
