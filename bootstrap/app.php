<?php

use App\Http\Middleware\CaptureReferralCode;
use App\Http\Middleware\EnsureCustomerIsActive;
use App\Http\Middleware\EnsureTwoFactorAuthenticated;
use App\Http\Middleware\EnsureUserHasPermission;
use App\Http\Middleware\EnsureUserHasRole;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\SetLocale;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [
            SetLocale::class,
            CaptureReferralCode::class,
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
        ]);

        $middleware->alias([
            'role' => EnsureUserHasRole::class,
            'two_factor' => EnsureTwoFactorAuthenticated::class,
            'permission' => EnsureUserHasPermission::class,
            'customer.active' => EnsureCustomerIsActive::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->respond(function (Response $response, Throwable $e, Request $request) {
            // Preserve JSON / API responses without wrapping in HTML or Inertia
            if ($request->expectsJson()) {
                return $response;
            }

            // In local debug mode, preserve standard Laravel debug screen for 500 errors
            if (app()->hasDebugModeEnabled() && $response->getStatusCode() === 500) {
                return $response;
            }

            $statusCode = $response->getStatusCode();

            // Maintenance mode (503): Always serve zero-dependency standalone Blade view
            if ($statusCode === 503) {
                return $response;
            }

            // Inertia XHR requests: render Error.tsx via Inertia with preserved HTTP status
            if ($request->header('X-Inertia') && in_array($statusCode, [403, 404, 419, 429, 500], true)) {
                try {
                    return Inertia::render('Error', [
                        'status' => $statusCode,
                    ])->toResponse($request)->setStatusCode($statusCode);
                } catch (Throwable) {
                    return $response;
                }
            }

            // Normal browser requests: Laravel will render matching resources/views/errors/{status}.blade.php
            return $response;
        });
    })->create();
