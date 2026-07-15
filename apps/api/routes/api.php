<?php

declare(strict_types=1);

use App\Http\Controllers\Webhooks\ZoomWebhookController;
use Illuminate\Support\Facades\Route;

/*
| API routes. The `api` middleware group (throttle:api + bindings) is applied
| automatically. HTTP auth for portal/API endpoints is finalised in P1.8
| (ADR 0002); for now this hosts signature-verified inbound webhooks.
*/

Route::post('webhooks/zoom', ZoomWebhookController::class)
    ->middleware('zoom.signed')
    ->withoutMiddleware('throttle:api')
    ->name('webhooks.zoom');
