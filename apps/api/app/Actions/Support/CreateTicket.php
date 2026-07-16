<?php

declare(strict_types=1);

namespace App\Actions\Support;

use App\Enums\TicketAuthorType;
use App\Enums\TicketCategory;
use App\Enums\TicketPriority;
use App\Enums\TicketStatus;
use App\Events\TicketCreated;
use App\Jobs\CheckTicketSla;
use App\Models\ContactTimelineEvent;
use App\Models\Lead;
use App\Models\Ticket;
use App\Models\TicketMessage;
use App\Models\TicketRoute;
use App\Models\User;
use App\Support\Crm\PhoneNormalizer;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\UploadedFile;

/**
 * Creates a support ticket (PRD §6.13): the student's first message, per-category
 * SLA due-times, auto-routing to a team + assignee, a correlated CRM timeline
 * entry, the TicketCreated event (notifications), and the armed SLA jobs.
 */
final readonly class CreateTicket
{
    public function __construct(
        private RouteTicket $router,
        private StoreTicketAttachments $attachments,
        private RecordTicketTimeline $timeline,
    ) {}

    /**
     * @param  list<UploadedFile>  $files
     */
    public function handle(
        User $student,
        TicketCategory $category,
        string $subject,
        string $body,
        TicketPriority $priority = TicketPriority::Normal,
        array $files = [],
        string $channel = 'portal',
    ): Ticket {
        return app(TenantContext::class)->run($student->tenant, function () use ($student, $category, $subject, $body, $priority, $files, $channel): Ticket {
            $route = TicketRoute::query()->where('category', $category->value)->first();
            $firstMin = $route?->first_response_minutes ?? 240;
            $resMin = $route?->resolution_minutes ?? 2880;

            $lead = $this->correlateLead($student);

            $ticket = Ticket::query()->create([
                'tenant_id' => $student->tenant_id,
                'reference' => 'BJ-PENDING',
                'student_id' => $student->id,
                'lead_id' => $lead?->id,
                'category' => $category->value,
                'priority' => $priority->value,
                'status' => TicketStatus::Open->value,
                'subject' => $subject,
                'first_response_due_at' => now()->addMinutes($firstMin),
                'resolution_due_at' => now()->addMinutes($resMin),
            ]);
            $ticket->update(['reference' => sprintf('BJ-%06d', $ticket->id)]);

            $message = TicketMessage::query()->create([
                'tenant_id' => $student->tenant_id,
                'ticket_id' => $ticket->id,
                'author_id' => $student->id,
                'author_type' => TicketAuthorType::Student->value,
                'body' => $body,
                'channel' => $channel,
            ]);

            $this->attachments->handle($ticket, $message, $files);
            $this->router->handle($ticket);

            $this->timeline->handle(
                $ticket->fresh(),
                ContactTimelineEvent::TYPE_NOTE,
                "Raised support ticket {$ticket->reference}: {$subject}",
                ['category' => $category->value],
            );

            TicketCreated::dispatch($ticket->fresh());

            $this->armSla($ticket->fresh(), $firstMin);

            return $ticket->fresh();
        });
    }

    private function armSla(Ticket $ticket, int $firstMin): void
    {
        $warnMinutes = (int) round($firstMin * (float) config('support.sla_warning_pct', 0.75));

        CheckTicketSla::dispatch($ticket->id, 'warning')->delay(now()->addMinutes($warnMinutes));
        CheckTicketSla::dispatch($ticket->id, 'first_response')->delay($ticket->first_response_due_at);
        CheckTicketSla::dispatch($ticket->id, 'resolution')->delay($ticket->resolution_due_at);
    }

    private function correlateLead(User $student): ?Lead
    {
        $phone = $student->phone !== null ? PhoneNormalizer::normalize($student->phone) : null;
        $hasPhone = $phone !== null && $phone !== '';
        $hasEmail = $student->email !== null && $student->email !== '';
        if (! $hasPhone && ! $hasEmail) {
            return null;
        }

        return Lead::query()
            ->where('tenant_id', $student->tenant_id)
            ->whereNull('merged_into_id')
            ->where(function ($q) use ($phone, $hasPhone, $hasEmail, $student) {
                if ($hasPhone) {
                    $q->where('phone_normalized', $phone);
                }
                if ($hasEmail) {
                    $q->orWhere('email', $student->email);
                }
            })
            ->orderBy('id')
            ->first();
    }
}
