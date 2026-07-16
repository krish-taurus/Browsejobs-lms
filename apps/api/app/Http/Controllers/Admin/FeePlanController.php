<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Actions\Payments\CreateFeePlan;
use App\Actions\Payments\PreviewSchedule;
use App\Enums\BatchMemberStatus;
use App\Enums\BatchType;
use App\Enums\FeePlanStatus;
use App\Enums\FeePlanType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Payments\PreviewScheduleRequest;
use App\Http\Requests\Payments\StoreFeePlanRequest;
use App\Http\Resources\FeePlanResource;
use App\Models\Batch;
use App\Models\BatchMember;
use App\Models\FeePlan;
use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Admin fee-plan management (PRD §6.8). Gated by `can:manage-fees`; route-model
 * binding is tenant-scoped so a foreign tenant's ids 404.
 */
final class FeePlanController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $plans = FeePlan::query()
            ->with(['student:id,name,phone,email', 'batch:id,number,course_id', 'batch.course:id,name'])
            ->withCount(['instalments as paid_count' => fn ($q) => $q->where('status', 'paid')])
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->when($request->filled('batch_id'), fn ($q) => $q->where('batch_id', $request->integer('batch_id')))
            ->orderByDesc('id')
            ->paginate(25);

        return FeePlanResource::collection($plans)->response();
    }

    public function show(FeePlan $feePlan): JsonResponse
    {
        $feePlan->load([
            'student:id,name,phone,email',
            'batch:id,number,course_id', 'batch.course:id,name',
            'instalments' => fn ($q) => $q->orderBy('seq'),
            'ledgerEntries' => fn ($q) => $q->orderBy('occurred_at')->orderBy('id'),
            'receipts' => fn ($q) => $q->orderByDesc('id'),
        ]);

        return (new FeePlanResource($feePlan))->response();
    }

    public function store(StoreFeePlanRequest $request, CreateFeePlan $create): JsonResponse
    {
        $tenant = app(TenantContext::class)->get();
        $student = User::query()->findOrFail($request->integer('user_id'));
        $batch = Batch::query()->findOrFail($request->integer('batch_id'));

        $feePlan = $create->handle(
            $tenant,
            $student,
            $batch,
            FeePlanType::from($request->string('type')->toString()),
            $request->integer('emi_count') ?: 1,
            $request->integer('discount_paise'),
            $request->user(),
        );

        $feePlan->load('student:id,name,phone,email', 'batch:id,number,course_id', 'batch.course:id,name', 'instalments');

        return (new FeePlanResource($feePlan))->response()->setStatusCode(201);
    }

    public function preview(PreviewScheduleRequest $request, PreviewSchedule $schedule): JsonResponse
    {
        $total = (int) config('fees.registration_paise');
        $rows = $schedule->handle(
            FeePlanType::from($request->string('type')->toString()),
            $request->integer('emi_count') ?: 1,
            $total,
            $request->integer('discount_paise'),
        );

        return response()->json(['data' => ['total_paise' => $total, 'instalments' => $rows]]);
    }

    /** Paid batches, for the create-plan picker. */
    public function batches(): JsonResponse
    {
        $batches = Batch::query()
            ->where('type', BatchType::Paid->value)
            ->with('course:id,name')
            ->orderByDesc('id')
            ->get(['id', 'number', 'course_id']);

        return response()->json(['data' => $batches]);
    }

    /** Reserved / Payment-Pending members of a batch without an active plan. */
    public function candidates(Request $request): JsonResponse
    {
        $batchId = $request->integer('batch_id');

        $withPlan = FeePlan::query()
            ->where('batch_id', $batchId)
            ->where('status', FeePlanStatus::Active->value)
            ->pluck('user_id');

        $members = BatchMember::query()
            ->where('batch_id', $batchId)
            ->whereIn('status', [BatchMemberStatus::Reserved->value, BatchMemberStatus::PaymentPending->value])
            ->whereNotIn('user_id', $withPlan)
            ->with('student:id,name,phone,email')
            ->get()
            ->map(fn (BatchMember $m) => [
                'user_id' => $m->user_id,
                'name' => $m->student?->name,
                'phone' => $m->student?->phone,
                'status' => $m->status->value,
            ]);

        return response()->json(['data' => $members]);
    }
}
