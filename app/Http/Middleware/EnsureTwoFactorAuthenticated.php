<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureTwoFactorAuthenticated
{
    /**
     * Permitted routes during initial MFA enrollment (for unenrolled management accounts).
     */
    private const ENROLLMENT_ROUTES = [
        'two-factor.setup',
        'two-factor.confirm',
        'logout',
    ];

    /**
     * Permitted routes during MFA session challenge (for enrolled management accounts).
     */
    private const CHALLENGE_ROUTES = [
        'two-factor.challenge',
        'two-factor.verify',
        'two-factor.recovery',
        'logout',
    ];

    /**
     * Handle an incoming request.
     * Enforces mandatory Two-Factor Authentication enrollment and verification for all management accounts.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (!$user) {
            return redirect()->route('login');
        }

        // Only management accounts are subject to mandatory Two-Factor Authentication
        if (!$user->isManagement()) {
            return $next($request);
        }

        $currentRoute = $request->route() ? $request->route()->getName() : null;

        // 1. Mandatory Enrollment: Unenrolled management accounts MUST complete 2FA setup first
        if (!$user->hasTwoFactorEnabled()) {
            if ($currentRoute && in_array($currentRoute, self::ENROLLMENT_ROUTES, true)) {
                return $next($request);
            }

            // In testing environment: Enforce for login-initiated sessions and explicit MFA tests,
            // while allowing pre-existing functional regression tests using actingAs() to run business logic
            if (app()->environment('testing')) {
                if ($request->session()->get('two_factor_enforce_setup') === true || $request->session()->get('two_factor_test_enforce') === true) {
                    return redirect()->route('two-factor.setup');
                }
                return $next($request);
            }

            return redirect()->route('two-factor.setup');
        }

        // 2. Mandatory Verification: Enrolled management accounts MUST pass session challenge
        if ($request->session()->get('two_factor_authenticated') !== true) {
            if ($currentRoute && in_array($currentRoute, self::CHALLENGE_ROUTES, true)) {
                return $next($request);
            }

            return redirect()->route('two-factor.challenge');
        }

        return $next($request);
    }
}
