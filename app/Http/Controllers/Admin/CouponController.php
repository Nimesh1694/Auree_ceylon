<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreCouponRequest;
use App\Http\Requests\Admin\UpdateCouponRequest;
use App\Models\Category;
use App\Models\Coupon;
use App\Models\Product;
use App\Services\CouponService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CouponController extends Controller
{
    public function __construct(
        protected CouponService $couponService
    ) {}

    /**
     * Display listing of coupons.
     */
    public function index(Request $request): Response
    {
        $this->authorizePermission('coupons.view');

        $query = Coupon::with('creator')
            ->withCount('usages');

        if ($request->filled('search')) {
            $search = trim($request->search);
            $query->where(function ($q) use ($search) {
                $q->where('code', 'like', "%{$search}%")
                  ->orWhere('name', 'like', "%{$search}%");
            });
        }

        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }

        if ($request->filled('status')) {
            $isActive = $request->status === 'active';
            $query->where('is_active', $isActive);
        }

        $coupons = $query->latest('id')->paginate(15)->withQueryString();

        return Inertia::render('Admin/Marketing/Coupons/Index', [
            'coupons' => $coupons,
            'filters' => $request->only(['search', 'type', 'status']),
            'canManage' => $request->user()?->hasPermissionTo('coupons.create') ?? false,
            'isOwner' => $request->user()?->isOwner() ?? false,
        ]);
    }

    /**
     * Show the form for creating a new coupon.
     */
    public function create(): Response
    {
        $this->authorizePermission('coupons.create');

        return Inertia::render('Admin/Marketing/Coupons/Create', [
            'categories' => Category::select('id', 'name_en')->orderBy('name_en')->get(),
            'products' => Product::where('is_active', true)->select('id', 'name_en', 'sku')->orderBy('name_en')->get(),
            'isOwner' => auth()->user()?->isOwner() ?? false,
        ]);
    }

    /**
     * Store a newly created coupon in storage.
     */
    public function store(StoreCouponRequest $request): RedirectResponse
    {
        $coupon = $this->couponService->createCoupon($request->validated(), $request->user());

        return redirect()->route('admin.coupons.show', $coupon->id)
            ->with('success', "Coupon '{$coupon->code}' created successfully!");
    }

    /**
     * Display the specified coupon details and redemption history.
     */
    public function show(Coupon $coupon): Response
    {
        $this->authorizePermission('coupons.view');

        $coupon->load([
            'categories:id,name_en',
            'products:id,name_en,sku',
            'creator:id,name,email',
            'usages' => function ($q) {
                $q->with(['order:id,order_number,total_amount', 'customer:id,name,email'])
                  ->latest('id')
                  ->limit(50);
            },
        ]);

        return Inertia::render('Admin/Marketing/Coupons/Show', [
            'coupon' => $coupon,
            'canEdit' => auth()->user()?->hasPermissionTo('coupons.update') ?? false,
            'canToggle' => auth()->user()?->hasPermissionTo('coupons.toggle_active') ?? false,
            'canDelete' => auth()->user()?->isOwner() ?? false,
        ]);
    }

    /**
     * Show the form for editing the specified coupon.
     */
    public function edit(Coupon $coupon): Response
    {
        $this->authorizePermission('coupons.update');

        $coupon->load(['categories:id', 'products:id']);

        return Inertia::render('Admin/Marketing/Coupons/Edit', [
            'coupon' => $coupon,
            'categories' => Category::select('id', 'name_en')->orderBy('name_en')->get(),
            'products' => Product::where('is_active', true)->select('id', 'name_en', 'sku')->orderBy('name_en')->get(),
            'isOwner' => auth()->user()?->isOwner() ?? false,
        ]);
    }

    /**
     * Update the specified coupon in storage.
     */
    public function update(UpdateCouponRequest $request, Coupon $coupon): RedirectResponse
    {
        $this->couponService->updateCoupon($coupon, $request->validated(), $request->user());

        return redirect()->route('admin.coupons.show', $coupon->id)
            ->with('success', "Coupon '{$coupon->code}' updated successfully!");
    }

    /**
     * Toggle the active status of the specified coupon.
     */
    public function toggle(Coupon $coupon, Request $request): RedirectResponse
    {
        $newState = $this->couponService->toggleActive($coupon, $request->user());

        $statusLabel = $newState ? 'activated' : 'deactivated';
        return back()->with('success', "Coupon '{$coupon->code}' has been {$statusLabel}.");
    }

    /**
     * Remove the specified coupon from storage.
     */
    public function destroy(Coupon $coupon, Request $request): RedirectResponse
    {
        try {
            $code = $coupon->code;
            $this->couponService->deleteCoupon($coupon, $request->user());

            return redirect()->route('admin.coupons.index')
                ->with('success', "Coupon '{$code}' deleted successfully.");
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    /**
     * Authorize user against granular permission.
     */
    protected function authorizePermission(string $permission): void
    {
        $user = auth()->user();
        if (!$user || !$user->hasPermissionTo($permission)) {
            abort(403, "Unauthorized: missing [{$permission}] permission.");
        }
    }
}

