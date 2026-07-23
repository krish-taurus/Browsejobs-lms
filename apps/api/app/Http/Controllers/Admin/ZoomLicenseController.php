<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ZoomLicenseRequest;
use App\Models\ZoomLicense;
use Illuminate\Http\JsonResponse;

/**
 * The Zoom host-license pool (PRD §6.3). Gated `can:manage-batches`. Each license is a
 * Zoom user meetings can be hosted under. Licenses are not dedicated to anyone —
 * every session claims whichever license is free at its time slot (ADR 0043), so
 * two licenses cover any two overlapping classes regardless of who teaches them.
 */
final class ZoomLicenseController extends Controller
{
    public function index(): JsonResponse
    {
        $licenses = ZoomLicense::query()
            ->orderBy('label')
            ->get()
            ->map(fn (ZoomLicense $l) => [
                'id' => $l->id,
                'label' => $l->label,
                'zoom_user_id' => $l->zoom_user_id,
                'active' => $l->active,
            ]);

        return response()->json(['data' => $licenses]);
    }

    public function store(ZoomLicenseRequest $request): JsonResponse
    {
        $license = ZoomLicense::query()->create($request->validated());

        return response()->json(['data' => $license], 201);
    }

    public function update(ZoomLicenseRequest $request, ZoomLicense $zoomLicense): JsonResponse
    {
        $zoomLicense->update($request->validated());

        return response()->json(['data' => $zoomLicense->fresh()]);
    }

    public function destroy(ZoomLicense $zoomLicense): JsonResponse
    {
        $zoomLicense->delete();

        return response()->json(['ok' => true]);
    }
}
