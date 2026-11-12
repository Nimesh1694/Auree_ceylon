<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Services\TwoFactorAuthService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Inertia\Inertia;
use Inertia\Response;

class TwoFactorChallengeController extends Controller
{
    public function __construct(
        protected TwoFactorAuthService $twoFactorService
    ) {}

    /**
     * Display the 2FA challenge screen.
     */
    public function create(Request $request): Response|RedirectResponse
    {
        $user = $request->user();

        if (!$user) {
            return redirect()->route('login');
        }

        if (!$user->isManagement()) {
            return redirect()->intended(route('dashboard'));
        }

        if (!$user->hasTwoFactorEnabled()) {
            return redirect()->route('two-factor.setup');
        }

        if ($request->session()->get('two_factor_authenticated') === true) {
            return redirect()->intended(route('admin.dashboard'));
        }

        return Inertia::render('Auth/TwoFactorChallenge');
    }

    /**
     * Verify a 6-digit TOTP code.
     */
    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();

        if (!$user || !$user->hasTwoFactorEnabled()) {
            return redirect()->route('login');
        }

        $throttleKey = '2fa-verify:' . $user->id;

        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            $seconds = RateLimiter::availableIn($throttleKey);
            return back()->withErrors([
                'code' => "Too many verification attempts. Please try again in {$seconds} seconds.",
            ]);
        }

        $validated = $request->validate([
            'code' => ['required', 'string', 'size:6'],
        ]);

        $isValid = $this->twoFactorService->verifyCode($user->two_factor_secret, $validated['code']);

        if (!$isValid) {
            RateLimiter::hit($throttleKey, 60);

            AuditLog::log('two_factor_failed', $user, null, [
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);

            return back()->withErrors([
                'code' => 'The provided two-factor authentication code is invalid or has expired.',
            ]);
        }

        RateLimiter::clear($throttleKey);

        $request->session()->put('two_factor_authenticated', true);
        $request->session()->regenerate();

        AuditLog::log('two_factor_verified', $user, null, [
            'method' => 'totp',
            'ip' => $request->ip(),
        ]);

        return redirect()->intended(route('admin.dashboard'));
    }

    /**
     * Verify a backup recovery code.
     */
    public function recovery(Request $request): RedirectResponse
    {
        $user = $request->user();

        if (!$user || !$user->hasTwoFactorEnabled()) {
            return redirect()->route('login');
        }

        $throttleKey = '2fa-recovery:' . $user->id;

        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            $seconds = RateLimiter::availableIn($throttleKey);
            return back()->withErrors([
                'recovery_code' => "Too many attempts. Please try again in {$seconds} seconds.",
            ]);
        }

        $validated = $request->validate([
            'recovery_code' => ['required', 'string', 'min:8', 'max:20'],
        ]);

        $isValid = $this->twoFactorService->verifyAndConsumeRecoveryCode($user, $validated['recovery_code']);

        if (!$isValid) {
            RateLimiter::hit($throttleKey, 60);

            AuditLog::log('two_factor_recovery_failed', $user, null, [
                'ip' => $request->ip(),
            ]);

            return back()->withErrors([
                'recovery_code' => 'The provided recovery code is invalid or has already been used.',
            ]);
        }

        RateLimiter::clear($throttleKey);

        $request->session()->put('two_factor_authenticated', true);
        $request->session()->regenerate();

        $remainingCount = count($user->fresh()->two_factor_recovery_codes ?? []);

        return redirect()->intended(route('admin.dashboard'))->with(
            'warning',
            "You logged in using a backup recovery code. That code is now invalid. You have {$remainingCount} recovery codes remaining."
        );
    }
}

