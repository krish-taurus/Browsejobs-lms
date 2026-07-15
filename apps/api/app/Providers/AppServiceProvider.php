<?php

declare(strict_types=1);

namespace App\Providers;

use App\Models\User;
use App\Support\Otp\LogOtpNotifier;
use App\Support\Otp\OtpNotifier;
use App\Support\Tenancy\TenantContext;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(TenantContext::class);
        $this->app->bind(OtpNotifier::class, LogOtpNotifier::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Super admins bypass every gate; any other ability resolves to a
        // permission slug the user's roles may grant. Returning null lets a
        // non-match fall through to explicitly defined gates/policies.
        Gate::before(function (User $user, string $ability): ?bool {
            if ($user->hasRole('super-admin')) {
                return true;
            }

            return $user->hasPermission($ability) ?: null;
        });
    }
}
