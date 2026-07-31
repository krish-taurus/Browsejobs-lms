<?php

declare(strict_types=1);

namespace App\Http\Controllers\Employer;

use App\Actions\Employers\AcceptEmployerInvite;
use App\Actions\Employers\ClaimEmployerInvite;
use App\Http\Controllers\Controller;
use App\Http\Requests\Employers\AcceptInviteRequest;
use App\Http\Requests\Employers\ClaimInviteRequest;
use App\Http\Resources\EmployerMemberResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

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

    /**
     * Set the first password on an admin-onboarded account and sign in.
     *
     * Unauthenticated by necessity — the whole point is that this person has
     * no credential yet. The token carries the authority, and it is spent on
     * use.
     */
    public function claim(ClaimInviteRequest $request, ClaimEmployerInvite $claim): JsonResponse
    {
        $user = $claim->handle(
            $request->string('token')->toString(),
            $request->string('password')->toString(),
        );

        // Signed straight in: bouncing someone to a login form immediately
        // after they chose a password is a step that exists for nobody.
        Auth::guard('web')->login($user);
        $request->session()->regenerate();

        return response()->json(['data' => [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
        ]], 201);
    }
}
