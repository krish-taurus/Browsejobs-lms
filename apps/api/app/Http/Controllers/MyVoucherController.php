<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\VoucherIssueStatus;
use App\Http\Resources\VoucherIssueResource;
use App\Models\VoucherIssue;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * A student's redeemable vouchers (PRD §5 Stage 3). Scoped to the signed-in user;
 * this route has no tenant middleware, so it filters by user_id directly.
 */
final class MyVoucherController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $issues = VoucherIssue::query()
            ->where('user_id', $request->user()->id)
            ->where('status', VoucherIssueStatus::Issued->value)
            ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()))
            ->with('voucher')
            ->orderByDesc('id')
            ->get();

        return response()->json(['data' => VoucherIssueResource::collection($issues)]);
    }
}
