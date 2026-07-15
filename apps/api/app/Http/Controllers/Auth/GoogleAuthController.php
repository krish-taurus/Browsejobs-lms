<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Actions\Auth\AssignRoleToUser;
use App\Http\Controllers\Auth\Concerns\LogsInUsers;
use App\Http\Controllers\Controller;
use App\Models\Scopes\TenantScope;
use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Laravel\Socialite\Facades\Socialite;
use Throwable;

/**
 * Google sign-in (spec §7: optional Google). Find-or-create a student by the
 * verified Google email within the resolved tenant, then start the SPA session
 * and bounce back to the frontend. Config-gated on services.google.
 */
final class GoogleAuthController extends Controller
{
    use LogsInUsers;

    public function redirect(): RedirectResponse
    {
        abort_unless($this->enabled(), 404);

        return Socialite::driver('google')->redirect();
    }

    public function callback(Request $request, AssignRoleToUser $assignRole): RedirectResponse
    {
        abort_unless($this->enabled(), 404);

        $frontend = rtrim((string) config('app.frontend_url', 'http://localhost:3000'), '/');

        try {
            $googleUser = Socialite::driver('google')->user();
        } catch (Throwable) {
            return redirect()->to($frontend.'/student?error=google');
        }

        $email = $googleUser->getEmail();
        if (! is_string($email) || $email === '') {
            return redirect()->to($frontend.'/student?error=google');
        }

        $tenant = app(TenantContext::class)->get();

        $user = User::query()
            ->withoutGlobalScope(TenantScope::class)
            ->where('tenant_id', $tenant->id)
            ->where('email', $email)
            ->first();

        if ($user === null) {
            $user = User::query()->create([
                'tenant_id' => $tenant->id,
                'name' => $googleUser->getName() ?: $email,
                'email' => $email,
                'email_verified_at' => now(),
                'user_type' => 'student',
            ]);
            $assignRole->handle($user, 'student', actor: $user);
        }

        $this->startSession($request, $user);

        return redirect()->to($frontend.'/dashboard');
    }

    private function enabled(): bool
    {
        return (string) config('services.google.client_id') !== '';
    }
}
