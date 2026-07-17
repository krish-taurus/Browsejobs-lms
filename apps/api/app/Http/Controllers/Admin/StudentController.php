<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Actions\Roster\AddStudentToBatch;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\EnrollStudentRequest;
use App\Http\Resources\StudentResource;
use App\Models\Batch;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * The candidates directory (PRD §6.13 ops): every student across the tenant, filterable
 * by batch and searchable — the student-first view the batch-first roster never gave.
 *
 * Gated `can:manage-rosters`. Reallocation reuses the existing roster actions: adding to
 * an additional batch here, moving/removing via the member-scoped transfer/remove routes.
 */
final class StudentController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $students = User::query()
            ->where('user_type', 'student')
            ->with(['batchMemberships' => fn ($q) => $q->with(['batch:id,number,course_id', 'batch.course:id,name'])])
            ->when($request->filled('batch_id'), fn ($q) => $q->whereHas(
                'batchMemberships',
                fn ($m) => $m->where('batch_id', (int) $request->integer('batch_id')),
            ))
            ->when($request->filled('q'), function ($q) use ($request) {
                $term = '%'.$request->string('q')->toString().'%';
                $q->where(fn ($x) => $x->where('name', 'like', $term)
                    ->orWhere('email', 'like', $term)
                    ->orWhere('phone', 'like', $term));
            })
            ->orderBy('name')
            ->paginate(30);

        return StudentResource::collection($students)->response();
    }

    /**
     * Add this student to another batch, keeping their current ones. Capacity, the
     * duplicate-in-same-batch guard, and the audit entry are all in AddStudentToBatch.
     */
    public function enroll(EnrollStudentRequest $request, int $student, AddStudentToBatch $add): JsonResponse
    {
        $user = User::query()->where('user_type', 'student')->find($student);
        if ($user === null) {
            throw new NotFoundHttpException;
        }

        // Tenant-scoped find — a batch from another tenant resolves to null → 404.
        $batch = Batch::query()->find((int) $request->integer('batch_id'));
        if ($batch === null) {
            throw new NotFoundHttpException;
        }

        $member = $add->handle($batch, $user, actor: $request->user());

        return response()->json(['data' => $member->load('batch:id,number')], 201);
    }
}
