<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Support\CannedResponseRequest;
use App\Http\Resources\CannedResponseResource;
use App\Models\CannedResponse;
use Illuminate\Http\JsonResponse;

/**
 * Canned-response manager for the support desk (PRD §6.13). Gated by
 * `can:handle-tickets`; tenant-scoped route binding.
 */
final class CannedResponseController extends Controller
{
    public function index(): JsonResponse
    {
        $items = CannedResponse::query()->orderByDesc('id')->get();

        return CannedResponseResource::collection($items)->response();
    }

    public function store(CannedResponseRequest $request): JsonResponse
    {
        $canned = CannedResponse::query()->create($request->validated());

        return (new CannedResponseResource($canned))->response()->setStatusCode(201);
    }

    public function update(CannedResponseRequest $request, CannedResponse $cannedResponse): JsonResponse
    {
        $cannedResponse->update($request->validated());

        return (new CannedResponseResource($cannedResponse->fresh()))->response();
    }

    public function destroy(CannedResponse $cannedResponse): JsonResponse
    {
        $cannedResponse->delete();

        return response()->json(['ok' => true]);
    }
}
