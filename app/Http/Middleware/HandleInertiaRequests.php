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

        $user = $request->user();
        if ($user && ($request->header('X-Inertia') || !$request->expectsJson())) {
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
                'unread_announcements_count' => fn () => ($user && $user->isAgent() && $user->status === 'active')
                    ? app(\App\Services\AgentAnnouncementService::class)->getUnreadCountForAgent($user)
                    : 0,
            ],
            'locale' => $locale,
            'translations' => fn () => File::exists($translationsPath)
                ? (json_decode(File::get($translationsPath), true) ?? [])
                : [],
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
                'generated_link' => fn () => $request->session()->get('generated_link'),
                'token' => fn () => $request->session()->get('token'),
                'candidate_name' => fn () => $request->session()->get('candidate_name'),
            ],
            'referralCode' => fn () => $request->session()->get('referral_code'),
            'cartCount' => fn () => count($request->session()->get('cart', [])),
            'cartDetails' => fn () => app(\App\Services\CartService::class)->getDetails(),
            'upsellProducts' => fn () => \App\Models\Product::with('category', 'primaryImage')
                ->where('is_active', true)
                ->where('stock_quantity', '>', 0)
                ->orderByDesc('is_featured')
                ->orderByDesc('id')
                ->limit(6)
                ->get()
                ->map(function ($p) {
                    return [
                        'id' => $p->id,
                        'name' => $p->name_en,
                        'slug' => $p->slug,
                        'price' => (float) $p->price,
                        'image' => $p->primaryImage?->path,
                        'category' => $p->category?->name_en ?? 'Ceylon Botanical',
                        'badge' => $p->is_featured ? 'Bestseller' : 'Ritual Pair',
                    ];
                }),
            'whatsapp' => [
                'number' => (string) \App\Models\Setting::get('whatsapp_number', env('VITE_WHATSAPP_NUMBER', '94772345678')),
                'message' => (string) \App\Models\Setting::get('whatsapp_message', 'Hello Aura Luxe, I would like to inquire about your products.'),
                'is_enabled' => (bool) \App\Models\Setting::get('whatsapp_enabled', true),
            ],
        ];
    }
}
