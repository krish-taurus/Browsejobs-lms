<?php

declare(strict_types=1);

namespace App\Providers;

use App\Models\User;
use App\Services\AI\AiClient;
use App\Services\AI\AnthropicClient;
use App\Services\AI\OpenAiCompatibleClient;
use App\Services\Crm\LeadScorer;
use App\Services\Crm\RuleBasedLeadScorer;
use App\Support\AI\ProviderResolver;
use App\Support\Certificates\CertificateRenderer;
use App\Support\Certificates\HtmlCertificateRenderer;
use App\Support\Fees\DuesFeeGate;
use App\Support\Fees\FeeGate;
use App\Support\Interviews\NullTranscriptionClient;
use App\Support\Interviews\TranscriptionClient;
use App\Support\JobFeed\HttpJSearchTransport;
use App\Support\JobFeed\JobApiTransport;
use App\Support\JobFeed\NullJobApiTransport;
use App\Support\Judge0\HttpJudge0Client;
use App\Support\Judge0\Judge0Client;
use App\Support\Market\MarketIntelSource;
use App\Support\Market\SeedMarketIntelSource;
use App\Support\Messaging\NullPushSender;
use App\Support\Messaging\PushSender;
use App\Support\Mocks\HttpVapiClient;
use App\Support\Mocks\NullVoiceMockClient;
use App\Support\Mocks\VoiceMockClient;
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
use App\Support\Settings\PlatformSettings;
use App\Support\Syllabus\HtmlSyllabusRenderer;
use App\Support\Syllabus\SyllabusRenderer;
use App\Support\Tenancy\TenantContext;
use App\Support\WhatsApp\HttpWhatsAppClient;
use App\Support\WhatsApp\WhatsAppClient;
use App\Support\Zoom\HttpZoomClient;
use App\Support\Zoom\ZoomClient;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use RuntimeException;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(
            MarketIntelSource::class,
            SeedMarketIntelSource::class,
        );

        $this->app->singleton(TenantContext::class);
        $this->app->singleton(PlatformSettings::class);

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
        $this->app->bind(CertificateRenderer::class, HtmlCertificateRenderer::class);

        // AI syllabus (P3.5c) renders to branded HTML now; WeasyPrint PDF swaps in
        // later behind the same interface, exactly like certificates/receipts.
        $this->app->bind(SyllabusRenderer::class, HtmlSyllabusRenderer::class);

        // Live job feed (P4.8, ADR 0045). JSearch when a RapidAPI key is set (legally
        // aggregates LinkedIn/Indeed/Naukri via Google-for-Jobs — no scraping), else
        // the Null transport (safe no-op). Tests bind a fake.
        $this->app->bind(JobApiTransport::class, function (): JobApiTransport {
            /** @var array{api_key: string, host: string} $config */
            $config = config('services.jsearch');

            return $config['api_key'] !== '' ? new HttpJSearchTransport($config) : new NullJobApiTransport;
        });

        // Messaging hub (P2.4). Real WhatsApp client from config; tests bind a fake.
        $this->app->bind(WhatsAppClient::class, function (): HttpWhatsAppClient {
            /** @var array{phone_number_id: string, access_token: string, base_url: string} $config */
            $config = config('services.whatsapp');

            return new HttpWhatsAppClient($config);
        });

        // Web push is deferred in P2.4.
        $this->app->bind(PushSender::class, NullPushSender::class);

        // AI Service Layer (P3.1). The transport is provider-switchable via
        // AI_PROVIDER (auto | anthropic | openai | kimi | deepseek | grok |
        // custom — see config/ai.php); tests bind FakeAiClient. The AiGateway
        // (budget + ai_events) composes this, so switching vendors changes
        // nothing else. ProviderResolver picks the effective provider: the
        // chosen one when it has a key, else the first provider that does — so
        // `auto` (or a keyless choice) transparently uses whatever is set up.
        $this->app->bind(AiClient::class, function (): AiClient {
            $provider = app(ProviderResolver::class)->resolve();
            $config = config("ai.providers.{$provider}");

            if (! is_array($config)) {
                throw new RuntimeException("Unknown AI provider [{$provider}] — see config/ai.php.");
            }

            return match ($config['driver'] ?? null) {
                'anthropic' => new AnthropicClient($config),
                'openai_compatible' => new OpenAiCompatibleClient($config),
                default => throw new RuntimeException("Unknown AI driver for provider [{$provider}]."),
            };
        });

        // Interview transcript speech-to-text (P4.2). Null until a provider is
        // configured; tests bind FakeTranscriptionClient. Text uploads bypass this.
        $this->app->bind(TranscriptionClient::class, NullTranscriptionClient::class);

        // Voice mock transport (P4.3). Vapi when a key is configured, else Null
        // (start fails loudly and refunds the credit); tests bind the fake.
        $this->app->bind(VoiceMockClient::class, function (): VoiceMockClient {
            /** @var array{api_key: string, base_url: string, webhook_secret: string} $config */
            $config = config('services.vapi');

            return $config['api_key'] !== '' ? new HttpVapiClient($config) : new NullVoiceMockClient;
        });

        // Coding labs code execution (P3.1). Real Judge0 client from config; tests
        // bind FakeJudge0Client.
        $this->app->bind(Judge0Client::class, function (): HttpJudge0Client {
            /** @var array{url: string, auth_token: string} $config */
            $config = config('services.judge0');

            return new HttpJudge0Client($config);
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Admin-entered integration keys (LLM/WhatsApp/Vapi/Zoom) override config here,
        // before any client binding resolves; blank/missing settings fall back to .env.
        $this->app->make(PlatformSettings::class)->apply();

        // A queue worker boots once and stays up, so a key saved in the admin panel
        // afterwards would never reach it. Re-apply the stored settings before each
        // job so workers pick up new keys without a manual restart.
        Queue::before(function (): void {
            $this->app->make(PlatformSettings::class)->refresh();
        });

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

        // Staff login: 6/min in production; env-raisable so the parallel e2e
        // suite (18 sign-ins in seconds) doesn't trip it in CI.
        RateLimiter::for('staff-login', fn (Request $request) => Limit::perMinute(
            (int) config('auth.staff_login_per_minute', 6),
        )->by($request->ip()));

        // Tight limit for AI-backed endpoints (CLAUDE.md: rate limit AI endpoints).
        RateLimiter::for('ai', fn (Request $request) => Limit::perMinute(20)->by(
            $request->user()?->getAuthIdentifier() ?? $request->ip(),
        ));

        // Note: the P2.4 SendLeadWelcomeMessage listener on LeadCaptured is
        // auto-registered by Laravel 11's app/Listeners discovery — no explicit
        // Event::listen needed.
    }
}
