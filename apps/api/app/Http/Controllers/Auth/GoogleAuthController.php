<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Actions\Auth\AssignRoleToUser;
use App\Actions\Auth\GrantSignupCredits;
use App\Http\Controllers\Auth\Concerns\LogsInUsers;
use App\Http\Controllers\Controller;
use App\Models\Scopes\TenantScope;
use App\Models\User;
use App\Support\Auth\SafeNextPath;
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

    public function redirect(Request $request): RedirectResponse
    {
        abort_unless($this->enabled(), 404);

        $next = SafeNextPath::check($request->query('next'));
        if ($next !== null) {
            $request->session()->put('auth.intended', $next);
        }

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

            // Same free tier a phone/OTP signup gets (Sept 2026 fix) — Google
            // sign-in was creating the account and skipping this entirely, so
            // a student who signed up this way landed on "0 generations" with
            // no credit transaction to explain why.
            app(GrantSignupCredits::class)->handle($user);
        }

        $this->startSession($request, $user);

        return redirect()->to($frontend.$this->intendedPath($request));
    }

    private function intendedPath(Request $request): string
    {
        return SafeNextPath::check($request->session()->pull('auth.intended')) ?? '/dashboard';
    }

    private function enabled(): bool
    {
        return (string) config('services.google.client_id') !== '';
    }
}
