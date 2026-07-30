<?php

declare(strict_types=1);

namespace App\Http\Controllers\Employer;

use App\Actions\Employers\AcceptEmployerInvite;
use App\Http\Controllers\Controller;
use App\Http\Requests\Employers\AcceptInviteRequest;
use App\Http\Resources\EmployerMemberResource;
use Illuminate\Http\JsonResponse;

final class InviteController extends Controller
{
    public function accept(AcceptInviteRequest $request, AcceptEmployerInvite $acceptInvite): JsonResponse
    {
        $member = $acceptInvite->handle(
            $request->user(),
            $request->string('token')->toString(),
        );

        return (new EmployerMemberResource($member->load('user')))->response()->setStatusCode(201);
    }
}
