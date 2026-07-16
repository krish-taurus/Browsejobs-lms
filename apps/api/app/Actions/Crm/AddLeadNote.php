<?php

declare(strict_types=1);

namespace App\Actions\Crm;

use App\Models\ContactTimelineEvent;
use App\Models\Lead;
use App\Models\User;

/**
 * Logs a counselor note on the lead timeline and marks first touch (the
 * speed-to-lead clock stops on the first logged contact — PRD §6.12).
 */
final readonly class AddLeadNote
{
    public function __construct(private RecordTimelineEvent $timeline) {}

    public function handle(Lead $lead, string $body, ?User $actor = null): ContactTimelineEvent
    {
        if ($lead->first_touch_at === null) {
            $lead->first_touch_at = now();
            $lead->save();
        }

        return $this->timeline->handle($lead, ContactTimelineEvent::TYPE_NOTE, $body, null, $actor);
    }
}
