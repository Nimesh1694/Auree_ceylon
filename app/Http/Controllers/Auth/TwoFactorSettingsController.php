<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\TwoFactorAuthService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class TwoFactorSettingsController extends Controller
{
    public function __construct(
        protected TwoFactorAuthService $twoFactorService
    ) {}

    /**
     * Display the 2FA enrollment / setup view.
     */
    public function setup(Request $request): Response|RedirectResponse
    {
        $user = $request->user();

        if (!$user->isManagement()) {
            return redirect()->intended(route('dashboard'));
        }

        // If already enrolled, require valid 2FA session verification before allowing access
        if ($user->hasTwoFactorEnabled()) {
            if ($request->session()->get('two_factor_authenticated') !== true) {
                return redirect()->route('two-factor.challenge');
            }
            return redirect()->route('two-factor.recovery-codes');
        }

        // Retrieve or generate pending secret in session
        $pendingSecret = $request->session()->get('two_factor_pending_secret');
        if (!$pendingSecret) {
            $pendingSecret = $this->twoFactorService->generateSecretKey();
            $request->session()->put('two_factor_pending_secret', $pendingSecret);
        }

        $formattedSecret = implode(' ', str_split($pendingSecret, 4));
        $otpAuthUrl = $this->twoFactorService->getOtpAuthUrl(
            config('app.name', 'AuraLuxe'),
            $user->email,
            $pendingSecret
        );

        return Inertia::render('Auth/TwoFactorSetup', [
            'secret' => $pendingSecret,
            'formattedSecret' => $formattedSecret,
            'otpAuthUrl' => $otpAuthUrl,
            'isEnrolled' => $user->hasTwoFactorEnabled(),
        ]);
    }

    /**
     * Confirm initial 2FA setup by verifying code.
     */
    public function confirm(Request $request): RedirectResponse
    {
        $user = $request->user();

        // Security Guard: An already-enrolled user must authenticate their current 2FA session before altering MFA
        if ($user->hasTwoFactorEnabled() && $request->session()->get('two_factor_authenticated') !== true) {
            return redirect()->route('two-factor.challenge');
        }

        $validated = $request->validate([
            'code' => ['required', 'string', 'size:6'],
        ]);

        $pendingSecret = $request->session()->get('two_factor_pending_secret');
        if (!$pendingSecret) {
            return back()->withErrors(['code' => 'Setup session expired. Please refresh and try again.']);
        }

        try {
            $recoveryCodes = $this->twoFactorService->confirmTwoFactor($user, $pendingSecret, $validated['code']);

            $request->session()->forget('two_factor_pending_secret');
            $request->session()->forget('two_factor_enforce_setup');
            $request->session()->put('two_factor_authenticated', true);

            return redirect()->route('two-factor.recovery-codes')->with([
                'success' => 'Two-Factor Authentication successfully enabled!',
                'newRecoveryCodes' => $recoveryCodes,
            ]);
        } catch (Throwable $e) {
            return back()->withErrors(['code' => $e->getMessage()]);
        }
    }

    /**
     * Display current recovery codes.
     */
    public function recoveryCodes(Request $request): Response|RedirectResponse
    {
        $user = $request->user();

        if (!$user->hasTwoFactorEnabled()) {
            return redirect()->route('two-factor.setup');
        }

        return Inertia::render('Auth/TwoFactorRecoveryCodes', [
            'recoveryCodes' => $user->two_factor_recovery_codes ?? [],
            'newRecoveryCodes' => session('newRecoveryCodes'),
        ]);
    }

    /**
     * Regenerate new recovery codes.
     */
    public function regenerateCodes(Request $request): RedirectResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'password' => ['required', 'string'],
        ]);

        if (!Hash::check($validated['password'], $user->password)) {
            return back()->withErrors(['password' => 'Incorrect password.']);
        }

        try {
            $newCodes = $this->twoFactorService->regenerateRecoveryCodes($user);

            return back()->with([
                'success' => 'New recovery codes generated. Please copy and store them safely.',
                'newRecoveryCodes' => $newCodes,
            ]);
        } catch (Throwable $e) {
            return back()->withErrors(['error' => $e->getMessage()]);
        }
    }
}

