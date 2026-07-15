<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth\Concerns;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Establishes a Sanctum SPA session for a user: logs them into the web guard
 * and rotates the session id to prevent fixation.
 */
trait LogsInUsers
{
    protected function startSession(Request $request, User $user): void
    {
        Auth::guard('web')->login($user);
        $request->session()->regenerate();
    }
}
