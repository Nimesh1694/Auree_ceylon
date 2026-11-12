<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\File;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that is loaded on the first page visit.
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determine the current asset version.
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $locale = App::getLocale();
        $translationsPath = base_path("lang/{$locale}.json");
        $translations = File::exists($translationsPath)
            ? json_decode(File::get($translationsPath), true) ?? []
            : [];

        $user = $request->user();
        if ($user) {
            $user->loadMissing('agentProfile');
        }

        return [
            ...parent::share($request),
            'auth' => [
                'user' => $user,
                'permissions' => fn () => $user ? $user->getPermissionsList() : [],
                'is_owner' => fn () => $user ? $user->isOwner() : false,
                'is_super_admin' => fn () => $user ? $user->isSuperAdmin() : false,
                'is_business_authority' => fn () => $user ? $user->isBusinessAuthority() : false,
                'is_technical_authority' => fn () => $user ? $user->isTechnicalAuthority() : false,
            ],
            'locale' => $locale,
            'translations' => $translations,
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
                'generated_link' => fn () => $request->session()->get('generated_link'),
                'token' => fn () => $request->session()->get('token'),
                'candidate_name' => fn () => $request->session()->get('candidate_name'),
            ],
            'referralCode' => fn () => $request->session()->get('referral_code'),
            'cartCount' => fn () => count($request->session()->get('cart', [])),
        ];
    }
}
