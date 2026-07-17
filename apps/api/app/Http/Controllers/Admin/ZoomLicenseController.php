<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ZoomLicenseRequest;
use App\Models\User;
use App\Models\ZoomLicense;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

/**
 * The Zoom host-license pool (PRD §6.3). Gated `can:manage-batches`. Each license is a
 * Zoom user meetings can be hosted under; allocating one to a mentor means that mentor's
 * batch classes run on it, so concurrent classes don't fight over a single host.
 */
final class ZoomLicenseController extends Controller
{
    public function index(): JsonResponse
    {
        $licenses = ZoomLicense::query()
            ->with('mentor:id,name')
            ->orderBy('label')
            ->get()
            ->map(fn (ZoomLicense $l) => [
                'id' => $l->id,
                'label' => $l->label,
                'zoom_user_id' => $l->zoom_user_id,
                'mentor_id' => $l->mentor_id,
                'mentor' => $l->mentor?->name,
                'active' => $l->active,
            ]);

        // Trainers and mentors a license can be allocated to.
        $staff = User::query()
            ->whereHas('roles', fn ($q) => $q->whereIn('slug', ['trainer', 'mentor']))
            ->orderBy('name')
            ->get(['id', 'name']);

        return response()->json(['data' => $licenses, 'assignable' => $staff]);
    }

    public function store(ZoomLicenseRequest $request): JsonResponse
    {
        $this->assertMentorFree($request->input('mentor_id'));

        $license = ZoomLicense::query()->create($request->validated());

        return response()->json(['data' => $license], 201);
    }

    public function update(ZoomLicenseRequest $request, ZoomLicense $zoomLicense): JsonResponse
    {
        $this->assertMentorFree($request->input('mentor_id'), $zoomLicense->id);

        $zoomLicense->update($request->validated());

        return response()->json(['data' => $zoomLicense->fresh()]);
    }

    public function destroy(ZoomLicense $zoomLicense): JsonResponse
    {
        $zoomLicense->delete();

        return response()->json(['ok' => true]);
    }

    /** A mentor holds at most one license — surface the clash instead of a raw DB error. */
    private function assertMentorFree(mixed $mentorId, ?int $exceptId = null): void
    {
        if ($mentorId === null || $mentorId === '') {
            return;
        }

        $taken = ZoomLicense::query()
            ->where('mentor_id', (int) $mentorId)
            ->where('tenant_id', app(TenantContext::class)->id())
            ->when($exceptId !== null, fn ($q) => $q->where('id', '!=', $exceptId))
            ->exists();

        if ($taken) {
            throw ValidationException::withMessages(['mentor_id' => 'This mentor already holds a Zoom license.']);
        }
    }
}
