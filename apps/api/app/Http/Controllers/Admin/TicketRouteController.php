<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Support\UpdateTicketRouteRequest;
use App\Http\Resources\TicketRouteResource;
use App\Models\TicketRoute;
use Illuminate\Http\JsonResponse;

/**
 * Category → team routing + SLA config (PRD §6.13, admin-configurable). Gated by
 * `can:handle-tickets`; one row per category, tenant-scoped.
 */
final class TicketRouteController extends Controller
{
    public function index(): JsonResponse
    {
        $routes = TicketRoute::query()->with('supportTeam')->orderBy('id')->get();

        return TicketRouteResource::collection($routes)->response();
    }

    public function update(UpdateTicketRouteRequest $request, TicketRoute $ticketRoute): JsonResponse
    {
        $ticketRoute->update($request->validated());

        return (new TicketRouteResource($ticketRoute->fresh()->load('supportTeam')))->response();
    }
}
