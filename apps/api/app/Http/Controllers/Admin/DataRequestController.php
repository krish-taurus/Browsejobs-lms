<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\DataRequest;
use App\Models\User;
use App\Support\Audit\AuditLogger;
use App\Support\Dpdp\AnonymizeStudent;
use App\Support\Dpdp\DataExporter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * DPDP grievance-officer surface (PRD §8): process data-subject access/deletion
 * requests. Gated by can:manage-settings — a sensitive, super-admin operation.
 * Access compiles the subject's export; deletion anonymises their PII while
 * retaining legally-required transactional records. Every action is audited.
 */
final class DataRequestController extends Controller
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function index(): JsonResponse
    {
        $requests = DataRequest::query()
            ->with('user:id,name,email')
            ->orderByRaw("CASE status WHEN 'pending' THEN 0 ELSE 1 END")
            ->orderByDesc('id')
            ->limit(100)
            ->get()
            ->map(fn (DataRequest $r) => [
                'id' => $r->id,
                'type' => $r->type,
                'status' => $r->status,
                'reason' => $r->reason,
                'student' => $r->user?->name,
                'requested_at' => $r->created_at?->toIso8601String(),
                'processed_at' => $r->processed_at?->toIso8601String(),
            ]);

        return response()->json(['data' => $requests]);
    }

    public function process(Request $request, DataRequest $dataRequest, DataExporter $exporter, AnonymizeStudent $anonymizer): JsonResponse
    {
        abort_if($dataRequest->status !== DataRequest::STATUS_PENDING, 422, 'This request has already been processed.');

        $subject = User::query()->findOrFail($dataRequest->user_id);

        DB::transaction(function () use ($dataRequest, $subject, $exporter, $anonymizer, $request): void {
            if ($dataRequest->type === DataRequest::TYPE_ACCESS) {
                $dataRequest->export = $exporter->for($subject);
            } else {
                $anonymizer->handle($subject);
            }

            $dataRequest->status = DataRequest::STATUS_COMPLETED;
            $dataRequest->processed_by = $request->user()->id;
            $dataRequest->processed_at = now();
            $dataRequest->save();
        });

        $this->audit->log('data_request.'.$dataRequest->type.'.fulfilled', $dataRequest, [
            'subject_id' => $subject->id,
        ], $request->user());

        return response()->json(['data' => ['status' => $dataRequest->status]]);
    }

    public function reject(Request $request, DataRequest $dataRequest): JsonResponse
    {
        abort_if($dataRequest->status !== DataRequest::STATUS_PENDING, 422, 'This request has already been processed.');

        $dataRequest->update([
            'status' => DataRequest::STATUS_REJECTED,
            'reason' => $request->string('reason')->trim()->value() ?: $dataRequest->reason,
            'processed_by' => $request->user()->id,
            'processed_at' => now(),
        ]);

        $this->audit->log('data_request.rejected', $dataRequest, [], $request->user());

        return response()->json(['data' => ['status' => $dataRequest->status]]);
    }
}
