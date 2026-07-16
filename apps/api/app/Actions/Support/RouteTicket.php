<?php

declare(strict_types=1);

namespace App\Actions\Support;

use App\Enums\BatchMemberStatus;
use App\Models\Batch;
use App\Models\ContactTimelineEvent;
use App\Models\SupportTeam;
use App\Models\Ticket;
use App\Models\TicketRoute;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Routes a ticket to a team + assignee (PRD §6.13). Per the category's TicketRoute:
 * `team` round-robins over the SupportTeam members, `batch_trainer` resolves the
 * student's own batch trainer (falling back to the Trainer team), `admin`
 * round-robins over the tenant admins. Round-robin mirrors AssignLead.
 */
final readonly class RouteTicket
{
    public function __construct(private RecordTicketTimeline $timeline) {}

    public function handle(Ticket $ticket, ?User $actor = null): Ticket
    {
        $route = TicketRoute::query()->where('category', $ticket->category->value)->first();

        $team = $route?->supportTeam;
        $assignee = null;

        if ($route !== null) {
            $assignee = match ($route->routing) {
                TicketRoute::ROUTING_BATCH_TRAINER => $this->batchTrainer($ticket) ?? $this->roundRobinTeam($route, $team),
                TicketRoute::ROUTING_ADMIN => $this->next($this->adminPool($ticket->tenant_id), $route),
                default => $this->roundRobinTeam($route, $team),
            };
        }

        $ticket->update([
            'support_team_id' => $team?->id,
            'assignee_id' => $assignee?->id,
        ]);

        if ($assignee !== null) {
            $this->timeline->handle(
                $ticket,
                ContactTimelineEvent::TYPE_ASSIGNED,
                "Ticket {$ticket->reference} assigned to {$assignee->name}",
                ['assignee_id' => $assignee->id],
                $actor,
            );
        }

        return $ticket;
    }

    private function batchTrainer(Ticket $ticket): ?User
    {
        $occupying = array_map(fn (BatchMemberStatus $s) => $s->value, BatchMemberStatus::occupying());

        $batch = Batch::query()
            ->whereNotNull('trainer_id')
            ->whereHas('members', fn ($q) => $q->where('user_id', $ticket->student_id)->whereIn('status', $occupying))
            ->orderByDesc('id')
            ->first();

        return $batch?->trainer;
    }

    private function roundRobinTeam(TicketRoute $route, ?SupportTeam $team): ?User
    {
        if ($team === null) {
            return null;
        }

        return $this->next($team->members()->orderBy('users.id')->get(), $route);
    }

    /**
     * @return Collection<int, User>
     */
    private function adminPool(?int $tenantId): Collection
    {
        return User::query()
            ->where('tenant_id', $tenantId)
            ->whereHas('roles', fn ($q) => $q->whereIn('slug', ['admin', 'super-admin']))
            ->orderBy('id')
            ->get();
    }

    /**
     * @param  Collection<int, User>  $pool
     */
    private function next(Collection $pool, ?TicketRoute $route): ?User
    {
        if ($pool->isEmpty()) {
            return null;
        }

        $ids = $pool->pluck('id')->all();
        $pointer = $route?->round_robin_pointer;
        $currentIndex = $pointer !== null ? array_search($pointer, $ids, true) : false;
        $nextIndex = $currentIndex === false ? 0 : ($currentIndex + 1) % count($ids);
        $next = $pool->get($nextIndex);

        if ($route !== null && $next !== null) {
            $route->round_robin_pointer = $next->id;
            $route->save();
        }

        return $next;
    }
}
