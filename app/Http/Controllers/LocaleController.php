<?php

namespace App\Http\Controllers;

use App\Http\Middleware\SetLocale;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Session;

class LocaleController extends Controller
{
    /**
     * Switch application locale via secure POST endpoint.
     */
    public function switch(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'locale' => ['required', 'string', 'in:' . implode(',', SetLocale::SUPPORTED_LOCALES)],
        ]);

        $locale = $validated['locale'];
        Session::put('locale', $locale);
        App::setLocale($locale);

        $referrer = $request->headers->get('referer');
        if (empty($referrer)) {
            return redirect()->route('home');
        }

        $parsed = parse_url($referrer);
        $referrerHost = $parsed['host'] ?? null;
        $appHost = $request->getHost();

        // Prevent protocol-relative open redirects (e.g. //evil.com)
        if (str_starts_with($referrer, '//')) {
            return redirect()->route('home');
        }

        // If an absolute host is present, it must strictly match current application host
        if ($referrerHost && strtolower($referrerHost) !== strtolower($appHost)) {
            return redirect()->route('home');
        }

        // Clean out any obsolete ?lang= or ?locale= query parameters while preserving other valid filters
        if (isset($parsed['query'])) {
            parse_str($parsed['query'], $queryParams);
            unset($queryParams['lang'], $queryParams['locale']);
            $newQuery = http_build_query($queryParams);

            $path = $parsed['path'] ?? '/';
            $safeUrl = $path . ($newQuery !== '' ? '?' . $newQuery : '');
            if (!empty($parsed['fragment'])) {
                $safeUrl .= '#' . $parsed['fragment'];
            }
            return redirect($safeUrl);
        }

        return redirect()->to($referrer);
    }
}
