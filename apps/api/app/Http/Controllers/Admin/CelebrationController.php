<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Actions\Engagement\PublishCelebration;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreCelebrationRequest;
use App\Models\Celebration;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Admin offer celebrations (PRD §6.18). Creation REQUIRES recorded consent;
 * publishing broadcasts once (idempotent).
 */
final class CelebrationController extends Controller
{
    public function index(): JsonResponse
    {
        $celebrations = Celebration::query()
            ->with('student:id,name')
            ->orderByDesc('id')
            ->limit(100)
            ->get()
            ->map(fn (Celebration $c) => [
                'id' => $c->id,
                'student' => $c->student->name,
                'display' => $c->displayName(),
                'display_mode' => $c->display_mode,
                'role_title' => $c->role_title,
                'company' => $c->company,
                'published_at' => $c->published_at?->toDateTimeString(),
                'is_active' => $c->is_active,
            ]);

        return response()->json(['data' => $celebrations]);
    }

    public function store(StoreCelebrationRequest $request): JsonResponse
    {
        $celebration = Celebration::query()->create([
            'tenant_id' => app(TenantContext::class)->id(),
            'student_id' => (int) $request->input('student_id'),
            'display_mode' => $request->string('display_mode')->toString(),
            'anonymous_label' => $request->input('anonymous_label'),
            'role_title' => $request->string('role_title')->toString(),
            'company' => $request->input('company'),
            // The checkbox asserts the student's explicit consent; we record when.
            'consented_at' => now(),
            'created_by' => $request->user()->id,
        ]);

        return response()->json(['data' => $celebration], 201);
    }

    public function publish(Celebration $celebration, PublishCelebration $publish): JsonResponse
    {
        return response()->json(['data' => $publish->handle($celebration)]);
    }

    public function destroy(Request $request, Celebration $celebration): JsonResponse
    {
        $celebration->update(['is_active' => false]);

        return response()->json(status: 204);
    }
}
