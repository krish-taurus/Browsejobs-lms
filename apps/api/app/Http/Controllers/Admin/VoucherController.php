<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Enums\VoucherIssueStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Vouchers\StoreVoucherRequest;
use App\Http\Requests\Vouchers\UpdateVoucherRequest;
use App\Http\Resources\VoucherResource;
use App\Models\Voucher;
use App\Models\VoucherIssue;
use App\Support\Audit\AuditLogger;
use Illuminate\Http\JsonResponse;

/**
 * Admin voucher manager (PRD §6.8). Gated by `can:manage-vouchers`; route-model
 * binding is tenant-scoped so a foreign tenant's ids 404. Vouchers reduce fees,
 * so create/update are audited.
 */
final class VoucherController extends Controller
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function index(): JsonResponse
    {
        $vouchers = Voucher::query()->withCount('issues')->orderByDesc('id')->get();

        return VoucherResource::collection($vouchers)->response();
    }

    public function store(StoreVoucherRequest $request): JsonResponse
    {
        $voucher = Voucher::query()->create($request->validated());

        $this->audit->log('voucher.created', $voucher, ['name' => $voucher->name], $request->user());

        return (new VoucherResource($voucher))->response()->setStatusCode(201);
    }

    public function update(UpdateVoucherRequest $request, Voucher $voucher): JsonResponse
    {
        $voucher->update($request->validated());

        $this->audit->log('voucher.updated', $voucher, [], $request->user());

        return (new VoucherResource($voucher->fresh()))->response();
    }

    public function destroy(Voucher $voucher): JsonResponse
    {
        // Deactivate rather than hard-delete — issued vouchers stay auditable.
        $voucher->update(['active' => false]);

        $this->audit->log('voucher.deactivated', $voucher);

        return response()->json(['ok' => true]);
    }

    public function analytics(): JsonResponse
    {
        $issued = VoucherIssue::query()->count();
        $applied = VoucherIssue::query()->where('status', VoucherIssueStatus::Applied->value)->count();
        $discount = (int) VoucherIssue::query()
            ->where('status', VoucherIssueStatus::Applied->value)
            ->sum('discount_paise');

        return response()->json(['data' => [
            'issued' => $issued,
            'applied' => $applied,
            'discount_paise' => $discount,
        ]]);
    }
}
