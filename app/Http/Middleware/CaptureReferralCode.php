<?php

namespace App\Http\Middleware;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CaptureReferralCode
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $referralCode = $request->query('ref');

        if ($referralCode) {
            $referralCode = strtoupper(trim($referralCode));

            // Validate that referral code belongs to an active agent
            $isValidAgent = User::where('referral_code', $referralCode)
                ->whereIn('role', [UserRole::HIGH_LEVEL_AGENT, UserRole::LOW_LEVEL_AGENT])
                ->where('status', UserStatus::ACTIVE)
                ->exists();

            if ($isValidAgent) {
                $request->session()->put('referral_code', $referralCode);
                $response = $next($request);
                // 30 days cookie (43200 minutes)
                return $response->withCookie(cookie('referral_code', $referralCode, 43200));
            }
        } elseif (!$request->session()->has('referral_code') && $request->hasCookie('referral_code')) {
            $cookieCode = strtoupper(trim((string) $request->cookie('referral_code')));
            $isValidAgent = User::where('referral_code', $cookieCode)
                ->whereIn('role', [UserRole::HIGH_LEVEL_AGENT, UserRole::LOW_LEVEL_AGENT])
                ->where('status', UserStatus::ACTIVE)
                ->exists();

            if ($isValidAgent) {
                $request->session()->put('referral_code', $cookieCode);
            }
        }

        return $next($request);
    }
}

