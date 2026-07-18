<?php

declare(strict_types=1);

namespace App\Http\Controllers\Me;

use App\Http\Controllers\Controller;
use App\Models\DataRequest;
use App\Support\Audit\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Student-facing DPDP requests (PRD §8): raise an access or deletion request and
 * see its status. A completed access request exposes the compiled export to its
 * owner only.
 */
final class DataRequestController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $requests = DataRequest::query()
            ->where('user_id', $request->user()->id)
            ->orderByDesc('id')
            ->get()
            ->map(fn (DataRequest $r) => [
                'id' => $r->id,
                'type' => $r->type,
                'status' => $r->status,
                'reason' => $r->reason,
                'has_export' => $r->export !== null,
                'requested_at' => $r->created_at?->toIso8601String(),
                'processed_at' => $r->processed_at?->toIso8601String(),
            ]);

        return response()->json(['data' => $requests]);
    }

    public function store(Request $request, AuditLogger $audit): JsonResponse
    {
        $data = $request->validate([
            'type' => ['required', Rule::in([DataRequest::TYPE_ACCESS, DataRequest::TYPE_DELETION])],
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        $user = $request->user();

        $open = DataRequest::query()
            ->where('user_id', $user->id)
            ->where('type', $data['type'])
            ->where('status', DataRequest::STATUS_PENDING)
            ->exists();

        abort_if($open, 422, 'You already have a pending request of this type.');

        $req = DataRequest::query()->create([
            'tenant_id' => $user->tenant_id,
            'user_id' => $user->id,
            'type' => $data['type'],
            'status' => DataRequest::STATUS_PENDING,
            'reason' => $data['reason'] ?? null,
        ]);

        $audit->log('data_request.raised', $req, ['type' => $req->type], $user);

        return response()->json(['data' => ['id' => $req->id, 'status' => $req->status]], 201);
    }

    public function show(Request $request, DataRequest $dataRequest): JsonResponse
    {
        abort_unless($dataRequest->user_id === $request->user()->id, 404);

        return response()->json(['data' => [
            'type' => $dataRequest->type,
            'status' => $dataRequest->status,
            'export' => $dataRequest->type === DataRequest::TYPE_ACCESS ? $dataRequest->export : null,
        ]]);
    }
}
