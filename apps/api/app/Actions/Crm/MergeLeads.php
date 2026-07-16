<?php

declare(strict_types=1);

namespace App\Actions\Crm;

use App\Models\ContactTimelineEvent;
use App\Models\Lead;
use App\Models\User;
use App\Support\Audit\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Folds a duplicate lead into a survivor (PRD §6.12 "merge tool for
 * duplicates"). Default survivor is the oldest lead (stable id + original UTM
 * attribution); the caller may override. Blank survivor fields are back-filled
 * from the loser, both timelines + tasks re-point to the survivor, the loser is
 * tombstoned, and the merge is audited.
 */
final readonly class MergeLeads
{
    /** @var list<string> Fields back-filled onto the survivor when it is blank. */
    private const FOLDABLE = [
        'email', 'course_slug', 'message', 'utm_source', 'utm_medium', 'utm_campaign', 'page',
    ];

    public function __construct(
        private RecordTimelineEvent $timeline,
        private AuditLogger $audit,
    ) {}

    public function handle(Lead $a, Lead $b, ?Lead $survivor = null, ?User $actor = null): Lead
    {
        if ($a->tenant_id !== $b->tenant_id) {
            throw ValidationException::withMessages(['lead' => 'Leads belong to different tenants.']);
        }

        if ($a->id === $b->id) {
            throw ValidationException::withMessages(['lead' => 'Cannot merge a lead into itself.']);
        }

        // Default: the older lead survives (lowest id == earliest created).
        [$keep, $lose] = $survivor?->id === $b->id
            ? [$b, $a]
            : ($survivor?->id === $a->id ? [$a, $b] : ($a->id <= $b->id ? [$a, $b] : [$b, $a]));

        return DB::transaction(function () use ($keep, $lose, $actor): Lead {
            foreach (self::FOLDABLE as $field) {
                if (($keep->{$field} === null || $keep->{$field} === '') && ! empty($lose->{$field})) {
                    $keep->{$field} = $lose->{$field};
                }
            }
            $keep->score = max($keep->score, $lose->score);
            $keep->save();

            $lose->timelineEvents()->update(['lead_id' => $keep->id]);
            $lose->tasks()->update(['lead_id' => $keep->id]);

            $lose->merged_into_id = $keep->id;
            $lose->save();

            $this->timeline->handle(
                $keep,
                ContactTimelineEvent::TYPE_MERGED,
                "Merged duplicate lead #{$lose->id} ({$lose->name}) into this record.",
                ['merged_lead_id' => $lose->id],
                $actor,
            );

            $this->audit->log(
                action: 'crm.lead_merged',
                target: $keep,
                metadata: ['survivor_id' => $keep->id, 'merged_id' => $lose->id],
                actor: $actor,
            );

            return $keep->refresh();
        });
    }
}
