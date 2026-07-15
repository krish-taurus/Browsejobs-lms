<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Actions\Auth\ConsumeMagicLink;
use App\Exceptions\InvalidMagicLinkException;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;

/**
 * Consume-and-redirect endpoint for magic links. The `signed` middleware
 * guarantees the token was not tampered with; this then single-use-consumes
 * the link, authenticates the target user, and lands them on the action.
 */
final class MagicLinkController extends Controller
{
    public function consume(string $token, ConsumeMagicLink $consume): RedirectResponse
    {
        try {
            $link = $consume->handle($token);
        } catch (InvalidMagicLinkException $e) {
            abort(403, $e->getMessage());
        }

        if ($link->user_id !== null) {
            Auth::loginUsingId($link->user_id);
        }

        $redirect = is_string($link->payload['redirect'] ?? null)
            ? $link->payload['redirect']
            : config('app.frontend_url', '/');

        return redirect()->to($redirect);
    }
}
