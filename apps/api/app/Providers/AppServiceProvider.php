<?php

declare(strict_types=1);

namespace App\Providers;

use App\Models\User;
use App\Services\Crm\LeadScorer;
use App\Services\Crm\RuleBasedLeadScorer;
use App\Support\Fees\DuesFeeGate;
use App\Support\Fees\FeeGate;
use App\Support\Notifications\FeeNotifier;
use App\Support\Notifications\LogFeeNotifier;
use App\Support\Notifications\LogSessionNotifier;
use App\Support\Notifications\SessionNotifier;
use App\Support\Otp\LogOtpNotifier;
use App\Support\Otp\OtpNotifier;
use App\Support\Razorpay\HttpRazorpayClient;
use App\Support\Razorpay\RazorpayClient;
use App\Support\Receipts\HtmlReceiptRenderer;
use App\Support\Receipts\ReceiptRenderer;
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

        // Real fee gate (P2.3): denies access when a student has an active
        // fee-driven block. AllowAllFeeGate stays available for tests to rebind.
        $this->app->bind(FeeGate::class, DuesFeeGate::class);

        // Dunning notifications log locally until the P2.4 messaging hub.
        $this->app->bind(FeeNotifier::class, LogFeeNotifier::class);

        // Live-class notifications log locally until the P2.4 messaging hub.
        $this->app->bind(SessionNotifier::class, LogSessionNotifier::class);

        // CRM lead scoring is rule-based in P2.1; the AI telemetry model (P3)
        // will rebind this interface.
        $this->app->bind(LeadScorer::class, RuleBasedLeadScorer::class);

        // Payments (P2.2). Real Razorpay client from config; tests bind a fake.
        $this->app->bind(RazorpayClient::class, function (): HttpRazorpayClient {
            /** @var array{key_id: string, key_secret: string, webhook_secret: string, base_url: string} $config */
            $config = config('services.razorpay');

            return new HttpRazorpayClient($config);
        });

        // GST receipts render to branded HTML now; WeasyPrint PDF swaps in later.
        $this->app->bind(ReceiptRenderer::class, HtmlReceiptRenderer::class);
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
