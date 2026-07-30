<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\EmployerInvite;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The raw token is deliberately never serialized — it travels only in
 * the invite email (CLAUDE.md magic-link rules).
 *
 * @mixin EmployerInvite
 */
final class EmployerInviteResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'email' => $this->email,
            'role' => $this->role->value,
            'expires_at' => $this->expires_at->toIso8601String(),
            'accepted_at' => $this->accepted_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
