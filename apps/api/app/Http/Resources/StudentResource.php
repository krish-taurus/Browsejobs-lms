<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Enums\BatchMemberStatus;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A candidate/student for the admin candidates view (PRD §6.13 ops). Exposes each
 * current batch membership with its `member_id`, so the reallocation UI can move or
 * remove a specific membership.
 *
 * @mixin User
 */
final class StudentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $occupying = BatchMemberStatus::occupying();

        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            // Only current memberships — transferred/dropped rows are history, not a
            // batch the student is in. The `member_id` is what move/remove act on.
            'batches' => $this->whenLoaded('batchMemberships', fn () => $this->batchMemberships
                ->filter(fn ($m) => in_array($m->status, $occupying, true))
                ->map(fn ($m) => [
                    'member_id' => $m->id,
                    'batch_id' => $m->batch_id,
                    'number' => $m->batch?->number,
                    'course' => $m->batch?->course?->name,
                    'status' => $m->status instanceof BatchMemberStatus ? $m->status->value : $m->status,
                ])
                ->values()
                ->all()),
        ];
    }
}
