<?php

declare(strict_types=1);

namespace App\Http\Controllers\Support;

use App\Actions\Support\CreateTicket;
use App\Actions\Support\PostTicketReply;
use App\Actions\Support\ReopenTicket;
use App\Actions\Support\SubmitCsat;
use App\Enums\TicketCategory;
use App\Enums\TicketPriority;
use App\Http\Controllers\Controller;
use App\Http\Requests\Support\CsatRequest;
use App\Http\Requests\Support\ReplyTicketRequest;
use App\Http\Requests\Support\StoreTicketRequest;
use App\Http\Resources\TicketResource;
use App\Models\Ticket;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Student "My Tickets" (PRD §6.13). Runs under auth:sanctum without tenant.user,
 * so every ticket is fetched by `student_id` (the ownership + tenant boundary).
 */
final class StudentTicketController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $tickets = Ticket::query()
            ->withoutGlobalScopes()
            ->where('student_id', $request->user()->id)
            ->with('assignee:id,name')
            ->orderByDesc('id')
            ->get();

        return TicketResource::collection($tickets)->response();
    }

    public function store(StoreTicketRequest $request, CreateTicket $create): JsonResponse
    {
        $ticket = $create->handle(
            $request->user(),
            TicketCategory::from($request->string('category')->toString()),
            $request->string('subject')->toString(),
            $request->string('body')->toString(),
            $request->filled('priority')
                ? TicketPriority::from($request->string('priority')->toString())
                : TicketPriority::Normal,
            array_values($request->file('attachments', [])),
        );

        $ticket->load('assignee:id,name', 'messages');

        return (new TicketResource($ticket))->response()->setStatusCode(201);
    }

    public function show(Request $request, int $ticket): JsonResponse
    {
        $model = $this->owned($request, $ticket);
        $model->load([
            'assignee:id,name', 'assignee.roles:id,name', 'supportTeam',
            'messages' => fn ($q) => $q->where('is_internal', false)->orderBy('id'),
            'messages.author:id,name', 'messages.attachments',
        ]);

        return (new TicketResource($model))->response();
    }

    public function reply(ReplyTicketRequest $request, int $ticket, PostTicketReply $reply): JsonResponse
    {
        $model = $this->owned($request, $ticket);
        $reply->handle($model, $request->user(), $request->string('body')->toString(), false, 'portal', array_values($request->file('attachments', [])));

        return response()->json(['ok' => true]);
    }

    public function reopen(Request $request, int $ticket, ReopenTicket $reopen): JsonResponse
    {
        $model = $this->owned($request, $ticket);
        $reopen->handle($model);

        return response()->json(['ok' => true]);
    }

    public function csat(CsatRequest $request, int $ticket, SubmitCsat $csat): JsonResponse
    {
        $model = $this->owned($request, $ticket);
        $csat->handle($model, $request->integer('rating'), $request->input('comment'));

        return response()->json(['ok' => true]);
    }

    private function owned(Request $request, int $ticketId): Ticket
    {
        return Ticket::query()
            ->withoutGlobalScopes()
            ->where('id', $ticketId)
            ->where('student_id', $request->user()->id)
            ->firstOrFail();
    }
}
