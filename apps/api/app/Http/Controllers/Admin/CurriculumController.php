<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Course;
use Illuminate\Http\JsonResponse;

/**
 * Admin curriculum reads. Tenant comes from the authenticated user
 * (tenant.user middleware), so the global scope confines every query —
 * cross-tenant ids 404 at route binding.
 */
final class CurriculumController extends Controller
{
    public function index(): JsonResponse
    {
        $courses = Course::query()
            ->with('program:id,name')
            ->withCount('modules')
            ->orderBy('position')
            ->get(['id', 'program_id', 'code', 'name', 'slug', 'status', 'tagline', 'position']);

        return response()->json(['data' => $courses]);
    }

    public function show(Course $course): JsonResponse
    {
        $course->load([
            'modules' => fn ($q) => $q->orderBy('position'),
            'modules.topics' => fn ($q) => $q->orderBy('position'),
            'modules.topics.lessons' => fn ($q) => $q->orderBy('position'),
        ]);

        return response()->json(['data' => $course]);
    }
}
