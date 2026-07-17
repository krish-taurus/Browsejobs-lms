<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Actions\Support\AssignTicket;
use App\Actions\Support\ChangeTicketStatus;
use App\Actions\Support\ClearAiPriority;
use App\Actions\Support\DraftTicketReply;
use App\Actions\Support\EscalateTicket;
use App\Actions\Support\PostTicketReply;
use App\Actions\Support\TicketStudentContext;
use App\Enums\TicketPriority;
use App\Enums\TicketStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Support\AssignTicketRequest;
use App\Http\Requests\Support\ChangeStatusRequest;
use App\Http\Requests\Support\ClearAiPriorityRequest;
use App\Http\Requests\Support\ReplyTicketRequest;
use App\Http\Resources\TicketResource;
use App\Models\Ticket;
use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Staff ticket workspace (PRD §6.13). Gated by `can:handle-tickets`; route-model
 * binding is tenant-scoped so a foreign tenant's ids 404.
 */
final class TicketController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $tickets = Ticket::query()
            ->with(['student:id,name', 'assignee:id,name'])
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->when($request->filled('category'), fn ($q) => $q->where('category', $request->string('category')))
            ->when($request->string('assignee')->toString() === 'me', fn ($q) => $q->where('assignee_id', $request->user()->id))
            ->when($request->boolean('breached'), fn ($q) => $q->where(fn ($x) => $x->whereNotNull('breached_at')->orWhereNotNull('resolution_breached_at')))
            // Priority first, then recency. Without this ordering "an angry payment
            // dispute jumps the queue" (PRD §6.13) would be decorative — the raise
            // would be recorded and nothing would move. CASE, not the enum's string
            // order, and portable across MySQL/SQLite.
            ->orderByRaw("CASE priority WHEN 'urgent' THEN 0 WHEN 'high' THEN 1 WHEN 'normal' THEN 2 ELSE 3 END")
            ->orderByDesc('id')
            ->paginate(25);

        return TicketResource::collection($tickets)->response();
    }

    public function show(Ticket $ticket, TicketStudentContext $context): JsonResponse
    {
        $ticket->load([
            'student:id,name,phone,email', 'assignee:id,name', 'assignee.roles:id,name', 'supportTeam',
            'messages' => fn ($q) => $q->orderBy('id'),
            'messages.author:id,name', 'messages.attachments',
        ]);

        return response()->json([
            'data' => new TicketResource($ticket),
            'context' => $ticket->student === null ? null : $context->handle($ticket->student),
        ]);
    }

    public function reply(ReplyTicketRequest $request, Ticket $ticket, PostTicketReply $reply): JsonResponse
    {
        $reply->handle(
            $ticket,
            $request->user(),
            $request->string('body')->toString(),
            $request->boolean('is_internal'),
            'portal',
            array_values($request->file('attachments', [])),
        );

        return response()->json(['ok' => true]);
    }

    public function status(ChangeStatusRequest $request, Ticket $ticket, ChangeTicketStatus $change): JsonResponse
    {
        $updated = $change->handle($ticket, TicketStatus::from($request->string('status')->toString()), $request->user());

        return (new TicketResource($updated))->response();
    }

    public function assign(AssignTicketRequest $request, Ticket $ticket, AssignTicket $assign): JsonResponse
    {
        $assignee = User::query()->findOrFail($request->integer('assignee_id'));
        $assign->handle($ticket, $assignee, $request->user());

        return response()->json(['ok' => true]);
    }

    public function escalate(Ticket $ticket, EscalateTicket $escalate, Request $request): JsonResponse
    {
        $escalate->handle($ticket, 'Manually escalated', $request->user());

        return response()->json(['ok' => true]);
    }

    /**
     * An AI-drafted reply suggestion (PRD §6.13). Returns a draft for the staff
     * member's reply box — it sends nothing. Null when there is no draft to offer.
     */
    public function draftReply(Ticket $ticket, DraftTicketReply $draft, Request $request): JsonResponse
    {
        return response()->json(['data' => $draft->handle($ticket, $request->user())]);
    }

    /** Staff undo for an AI priority raise. */
    public function clearAiPriority(ClearAiPriorityRequest $request, Ticket $ticket, ClearAiPriority $clear): JsonResponse
    {
        $updated = $clear->handle($ticket, TicketPriority::from($request->string('priority')->toString()), $request->user());

        return (new TicketResource($updated))->response();
    }

    /** Assignable staff (holders of handle-tickets) for the reassign picker. */
    public function staff(): JsonResponse
    {
        $users = User::query()
            ->where('tenant_id', app(TenantContext::class)->id())
            ->whereHas('roles.permissions', fn ($q) => $q->where('slug', 'handle-tickets'))
            ->orderBy('name')
            ->get(['id', 'name']);

        return response()->json(['data' => $users]);
    }
}
