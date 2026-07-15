<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Actions\Roster\AddStudentToBatch;
use App\Actions\Roster\FindOrCreateStudent;
use App\Actions\Roster\ImportRosterCsv;
use App\Actions\Roster\RemoveBatchMember;
use App\Actions\Roster\TransferBatchMember;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\RosterRequest;
use App\Models\Batch;
use App\Models\BatchMember;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;

/** Roster operations over the P1.4 actions — every mutation is audited there. */
final class RosterController extends Controller
{
    public function store(
        RosterRequest $request,
        Batch $batch,
        FindOrCreateStudent $findOrCreate,
        AddStudentToBatch $add,
    ): JsonResponse {
        $student = $findOrCreate->handle(
            app(TenantContext::class)->get(),
            $request->string('name')->toString(),
            $request->input('email'),
            $request->input('phone'),
        );

        $member = $add->handle($batch, $student, actor: $request->user());

        return response()->json(['data' => $member->load('student:id,name,email,phone')], 201);
    }

    public function import(RosterRequest $request, Batch $batch, ImportRosterCsv $import): JsonResponse
    {
        $summary = $import->handle(
            $batch,
            app(TenantContext::class)->get(),
            $request->string('csv')->toString(),
            $request->user(),
        );

        return response()->json(['data' => $summary]);
    }

    public function transfer(RosterRequest $request, BatchMember $member, TransferBatchMember $transfer): JsonResponse
    {
        $target = Batch::query()->findOrFail((int) $request->input('target_batch_id'));

        $moved = $transfer->handle(
            $member,
            $target,
            $request->string('reason')->toString(),
            $request->user(),
        );

        return response()->json(['data' => $moved]);
    }

    public function remove(RosterRequest $request, BatchMember $member, RemoveBatchMember $remove): JsonResponse
    {
        $remove->handle($member, $request->string('reason')->toString(), $request->user());

        return response()->json(['data' => $member->fresh()]);
    }
}
