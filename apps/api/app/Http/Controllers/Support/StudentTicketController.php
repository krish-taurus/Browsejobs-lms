<?php

declare(strict_types=1);

namespace App\Http\Controllers\Support;

use App\Actions\Support\CreateTicket;
use App\Actions\Support\DeflectTicket;
use App\Actions\Support\PostTicketReply;
use App\Actions\Support\ReopenTicket;
use App\Actions\Support\ResolveDeflection;
use App\Actions\Support\SubmitCsat;
use App\Enums\TicketCategory;
use App\Enums\TicketPriority;
use App\Http\Controllers\Controller;
use App\Http\Requests\Support\CsatRequest;
use App\Http\Requests\Support\DeflectTicketRequest;
use App\Http\Requests\Support\ReplyTicketRequest;
use App\Http\Requests\Support\StoreTicketRequest;
use App\Http\Resources\TicketDeflectionResource;
use App\Http\Resources\TicketResource;
use App\Models\Ticket;
use App\Models\TicketDeflection;
use App\Support\Tutor\CitationResolver;
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

    /**
     * The AI first-response layer (PRD §6.13) — offered before the ticket exists.
     *
     * Always 200. A null `deflection` means "no answer" (feature off, nothing to ground
     * on, low confidence, budget exhausted, or the model is down) and the client simply
     * raises the ticket. Deflection is assistive; it must never be able to block or
     * error a student out of reaching a human.
     */
    public function deflect(DeflectTicketRequest $request, DeflectTicket $deflect, CitationResolver $citations): JsonResponse
    {
        $deflection = $deflect->handle(
            $request->user(),
            TicketCategory::from($request->string('category')->toString()),
            $request->string('body')->toString(),
        );

        if ($deflection === null) {
            return response()->json(['data' => null]);
        }

        $deflection->setAttribute('citations', $citations->resolve($deflection->cited_chunk_ids ?? []));

        // Pinned to 200: the resource wraps a row that was just written, and Laravel
        // would infer 201 from `wasRecentlyCreated`. Nothing the student owns was
        // created — the audit row is ours, and the ticket is what a 201 would imply.
        return (new TicketDeflectionResource($deflection))->response()->setStatusCode(200);
    }

    /** The answer resolved it — no ticket. */
    public function acceptDeflection(Request $request, int $deflection, ResolveDeflection $resolve): JsonResponse
    {
        $resolve->accepted($this->ownedDeflection($request, $deflection));

        return response()->json(['ok' => true]);
    }

    public function store(StoreTicketRequest $request, CreateTicket $create, ResolveDeflection $resolve): JsonResponse
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

        // The student saw an answer and raised the ticket anyway — record what the
        // deflection failed to prevent. Never blocks ticket creation.
        if ($request->filled('deflection_id')) {
            $offered = TicketDeflection::query()
                ->withoutGlobalScopes()
                ->where('id', (int) $request->integer('deflection_id'))
                ->where('student_id', $request->user()->id)
                ->first();

            if ($offered !== null) {
                $resolve->proceeded($offered, $ticket);
            }
        }

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

    /** Same ownership boundary as `owned()` — `student_id` is the tenant guard here. */
    private function ownedDeflection(Request $request, int $deflectionId): TicketDeflection
    {
        return TicketDeflection::query()
            ->withoutGlobalScopes()
            ->where('id', $deflectionId)
            ->where('student_id', $request->user()->id)
            ->firstOrFail();
    }
}
