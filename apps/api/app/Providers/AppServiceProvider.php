<?php

declare(strict_types=1);

namespace App\Providers;

use App\Models\User;
use App\Support\Fees\AllowAllFeeGate;
use App\Support\Fees\FeeGate;
use App\Support\Otp\LogOtpNotifier;
use App\Support\Otp\OtpNotifier;
use App\Support\Tenancy\TenantContext;
use App\Support\Zoom\HttpZoomClient;
use App\Support\Zoom\ZoomClient;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
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

        $this->app->bind(ZoomClient::class, function (): HttpZoomClient {
            /** @var array{account_id: string, client_id: string, client_secret: string, base_url: string, oauth_url: string} $config */
            $config = config('services.zoom');

            return new HttpZoomClient($config);
        });

        // Fee gate is a stub until P2.3; callers depend on the interface.
        $this->app->bind(FeeGate::class, AllowAllFeeGate::class);
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

        RateLimiter::for('api', fn (Request $request) => Limit::perMinute(120)->by(
            $request->user()?->getAuthIdentifier() ?? $request->ip(),
        ));
    }
}
