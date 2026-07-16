<?php

declare(strict_types=1);

use App\Http\Middleware\ResolveTenantByDomain;
use App\Http\Middleware\ResolveTenantByUser;
use App\Http\Middleware\VerifyMetaWebhookSignature;
use App\Http\Middleware\VerifyRazorpayWebhookSignature;
use App\Http\Middleware\VerifyZoomWebhookSignature;
use Illuminate\Auth\Middleware\Authorize;
use Illuminate\Contracts\Auth\Middleware\AuthenticatesRequests;
use Illuminate\Contracts\Session\Middleware\AuthenticatesSessions;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Foundation\Http\Middleware\HandlePrecognitiveRequests;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Routing\Middleware\ThrottleRequestsWithRedis;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        // Sanctum SPA: requests from stateful (first-party) domains authenticate
        // via the session cookie (ADR 0004). CSRF is enforced for them.
        $middleware->statefulApi();

        $middleware->alias([
            'tenant.domain' => ResolveTenantByDomain::class,
            'tenant.user' => ResolveTenantByUser::class,
            'zoom.signed' => VerifyZoomWebhookSignature::class,
            'meta.signed' => VerifyMetaWebhookSignature::class,
            'razorpay.signed' => VerifyRazorpayWebhookSignature::class,
        ]);

        // Tenant resolution MUST run before route-model binding so the global
        // TenantScope confines implicit bindings — otherwise a foreign tenant's
        // ids would resolve on admin routes (covered by AdminApiTest).
        $middleware->priority([
            HandlePrecognitiveRequests::class,
            EncryptCookies::class,
            StartSession::class,
            ShareErrorsFromSession::class,
            AuthenticatesRequests::class,
            ThrottleRequests::class,
            ThrottleRequestsWithRedis::class,
            AuthenticatesSessions::class,
            ResolveTenantByUser::class,
            ResolveTenantByDomain::class,
            SubstituteBindings::class,
            Authorize::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
