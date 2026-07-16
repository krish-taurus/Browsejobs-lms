<?php

declare(strict_types=1);

namespace App\Providers;

use App\Models\User;
use App\Services\Crm\LeadScorer;
use App\Services\Crm\RuleBasedLeadScorer;
use App\Support\Fees\DuesFeeGate;
use App\Support\Fees\FeeGate;
use App\Support\Messaging\NullPushSender;
use App\Support\Messaging\PushSender;
use App\Support\Notifications\FeeNotifier;
use App\Support\Notifications\MessengerFeeNotifier;
use App\Support\Notifications\MessengerSessionNotifier;
use App\Support\Notifications\SessionNotifier;
use App\Support\Otp\MessengerOtpNotifier;
use App\Support\Otp\OtpNotifier;
use App\Support\Razorpay\HttpRazorpayClient;
use App\Support\Razorpay\RazorpayClient;
use App\Support\Receipts\HtmlReceiptRenderer;
use App\Support\Receipts\ReceiptRenderer;
use App\Support\Tenancy\TenantContext;
use App\Support\WhatsApp\HttpWhatsAppClient;
use App\Support\WhatsApp\WhatsAppClient;
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

        // OTP + live-class + dunning notifications route through the P2.4
        // messaging hub (Messenger). Log* stubs remain for tests to force-bind.
        $this->app->bind(OtpNotifier::class, MessengerOtpNotifier::class);

        $this->app->bind(ZoomClient::class, function (): HttpZoomClient {
            /** @var array{account_id: string, client_id: string, client_secret: string, base_url: string, oauth_url: string} $config */
            $config = config('services.zoom');

            return new HttpZoomClient($config);
        });

        // Real fee gate (P2.3): denies access when a student has an active
        // fee-driven block. AllowAllFeeGate stays available for tests to rebind.
        $this->app->bind(FeeGate::class, DuesFeeGate::class);

        $this->app->bind(FeeNotifier::class, MessengerFeeNotifier::class);
        $this->app->bind(SessionNotifier::class, MessengerSessionNotifier::class);

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

        // Messaging hub (P2.4). Real WhatsApp client from config; tests bind a fake.
        $this->app->bind(WhatsAppClient::class, function (): HttpWhatsAppClient {
            /** @var array{phone_number_id: string, access_token: string, base_url: string} $config */
            $config = config('services.whatsapp');

            return new HttpWhatsAppClient($config);
        });

        // Web push is deferred in P2.4.
        $this->app->bind(PushSender::class, NullPushSender::class);
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

        // Note: the P2.4 SendLeadWelcomeMessage listener on LeadCaptured is
        // auto-registered by Laravel 11's app/Listeners discovery — no explicit
        // Event::listen needed.
    }
}
