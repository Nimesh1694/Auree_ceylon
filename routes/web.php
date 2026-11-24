<?php

use App\Http\Controllers\Admin\AgentController as AdminAgentController;
use App\Http\Controllers\Admin\AdminBannerController;
use App\Http\Controllers\Admin\AgentAchievementAwardController as AdminAgentAchievementAwardController;
use App\Http\Controllers\Admin\AgentAchievementRequestController as AdminAgentAchievementRequestController;
use App\Http\Controllers\Admin\AgentAnalyticsController as AdminAgentAnalyticsController;
use App\Http\Controllers\Admin\AgentAnnouncementController as AdminAgentAnnouncementController;
use App\Http\Controllers\Admin\AgentInvitationController as AdminAgentInvitationController;
use App\Http\Controllers\Admin\ApprovalCenterController as AdminApprovalCenterController;
use App\Http\Controllers\Admin\CategoryController as AdminCategoryController;
use App\Http\Controllers\Admin\CommissionController as AdminCommissionController;
use App\Http\Controllers\Admin\CouponController as AdminCouponController;
use App\Http\Controllers\Admin\CourierController as AdminCourierController;
use App\Http\Controllers\Admin\CustomerController as AdminCustomerController;
use App\Http\Controllers\Admin\CustomerInquiryController as AdminCustomerInquiryController;
use App\Http\Controllers\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Admin\FinancialReportController as AdminFinancialReportController;
use App\Http\Controllers\Admin\HierarchyAuditController;
use App\Http\Controllers\Admin\InventoryController as AdminInventoryController;
use App\Http\Controllers\Admin\OrderController as AdminOrderController;
use App\Http\Controllers\Admin\PayoutController as AdminPayoutController;
use App\Http\Controllers\Admin\PermissionRequestController as AdminPermissionRequestController;
use App\Http\Controllers\Admin\ProductController as AdminProductController;
use App\Http\Controllers\Admin\ProductReviewController as AdminProductReviewController;
use App\Http\Controllers\Admin\PurchaseOrderController as AdminPurchaseOrderController;
use App\Http\Controllers\Admin\RoleController as AdminRoleController;
use App\Http\Controllers\Admin\StaffController as AdminStaffController;
use App\Http\Controllers\Admin\StockAlertController as AdminStockAlertController;
use App\Http\Controllers\Admin\SupplierController as AdminSupplierController;
use App\Http\Controllers\Admin\SystemHealthController as AdminSystemHealthController;
use App\Http\Controllers\Agent\AgentAchievementPortalController;
use App\Http\Controllers\Agent\AgentInvitationController as AgentInvitationController;
use App\Http\Controllers\Agent\AnalyticsController as AgentAnalyticsPortalController;
use App\Http\Controllers\Agent\AnnouncementController as AgentAnnouncementPortalController;
use App\Http\Controllers\Agent\CommissionController as AgentCommissionController;
use App\Http\Controllers\Agent\DashboardController as AgentDashboardController;
use App\Http\Controllers\Agent\OrderController as AgentOrderController;
use App\Http\Controllers\Agent\ProfileController as AgentProfileController;
use App\Http\Controllers\Agent\RegisterController as AgentRegisterController;
use App\Http\Controllers\Agent\TeamController as AgentTeamController;
use App\Http\Controllers\Agent\WalletController as AgentWalletController;
use App\Http\Controllers\Customer\AddressController as CustomerAddressController;
use App\Http\Controllers\Customer\DashboardController as CustomerDashboardController;
use App\Http\Controllers\Customer\OrderController as CustomerOrderController;
use App\Http\Controllers\Customer\RecentlyViewedProductController;
use App\Http\Controllers\LocaleController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\Public\BackInStockSubscriptionController;
use App\Http\Controllers\Public\CartController;
use App\Http\Controllers\Public\CategoryController;
use App\Http\Controllers\Public\CheckoutController;
use App\Http\Controllers\Public\HomeController;
use App\Http\Controllers\Public\PageController;
use App\Http\Controllers\Public\ProductController;
use App\Http\Controllers\Public\ProductReviewController as PublicProductReviewController;
use App\Http\Controllers\Public\TrackingController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

// Home & Storefront
Route::get('/', [HomeController::class, 'index'])->name('home');
Route::post('/locale', [LocaleController::class, 'switch'])->name('locale.switch');

// Products
Route::get('/api/products/batch', [ProductController::class, 'batch'])->name('products.batch');
Route::get('/products/batch', [ProductController::class, 'batch']);
Route::get('/products', [ProductController::class, 'index'])->name('products.index');
Route::get('/products/{slug}', [ProductController::class, 'show'])->name('products.show');
Route::get('/products/{product}/reviews', [PublicProductReviewController::class, 'index'])->name('products.reviews.index');
Route::post('/products/{product}/reviews', [PublicProductReviewController::class, 'store'])->middleware('auth')->name('products.reviews.store');

// Back-in-Stock Alerts (FV-03-05)
Route::post('/back-in-stock/subscribe', [BackInStockSubscriptionController::class, 'store'])
    ->middleware('throttle:10,1')
    ->name('back-in-stock.subscribe');
Route::get('/back-in-stock/verify', [BackInStockSubscriptionController::class, 'showVerify'])
    ->name('back-in-stock.verify');
Route::post('/back-in-stock/verify', [BackInStockSubscriptionController::class, 'verify'])
    ->middleware('throttle:20,1')
    ->name('back-in-stock.verify.confirm');
Route::get('/back-in-stock/unsubscribe/{subscription}', [BackInStockSubscriptionController::class, 'showUnsubscribe'])
    ->name('back-in-stock.unsubscribe');
Route::post('/back-in-stock/unsubscribe/{subscription}', [BackInStockSubscriptionController::class, 'unsubscribe'])
    ->name('back-in-stock.unsubscribe.confirm');

// Categories
Route::get('/categories', [CategoryController::class, 'index'])->name('categories.index');
Route::get('/categories/{slug}', [CategoryController::class, 'show'])->name('categories.show');

// Shopping Cart
Route::get('/cart', [CartController::class, 'index'])->name('cart.index');
Route::post('/cart/add', [CartController::class, 'add'])->name('cart.add');
Route::match(['patch', 'post'], '/cart/update', [CartController::class, 'update'])->name('cart.update');
Route::match(['delete', 'post'], '/cart/remove/{productId}', [CartController::class, 'remove'])->name('cart.remove');
Route::match(['delete', 'post'], '/cart/clear', [CartController::class, 'clear'])->name('cart.clear');

// Cash on Delivery (COD) Checkout (Rate Limited & Active Account Enforced)
Route::middleware('customer.active')->group(function () {
    Route::get('/checkout', [CheckoutController::class, 'index'])->name('checkout.index');
    Route::post('/checkout', [CheckoutController::class, 'store'])->middleware('throttle:10,1')->name('checkout.store');
    Route::post('/checkout/apply-coupon', [CheckoutController::class, 'applyCoupon'])->middleware('throttle:15,1')->name('checkout.apply_coupon');
    Route::post('/checkout/remove-coupon', [CheckoutController::class, 'removeCoupon'])->name('checkout.remove_coupon');
});
Route::get('/orders/{orderNumber}/confirmation', [CheckoutController::class, 'confirmation'])->name('order.confirmation');
Route::get('/orders/{orderNumber}/receipt', [CheckoutController::class, 'downloadReceipt'])->name('order.receipt');
Route::get('/orders/{orderNumber}/receipt/show', [CheckoutController::class, 'showReceipt'])->name('order.receipt.show');
Route::get('/orders/{orderNumber}/receipt/print', [CheckoutController::class, 'printReceipt'])->name('order.receipt.print');

// Secure Order Tracking (Rate Limited)
Route::get('/track', [TrackingController::class, 'index'])->name('tracking.index');
Route::post('/track', [TrackingController::class, 'track'])->middleware('throttle:15,1')->name('tracking.track');

// Additional Informational Pages
Route::get('/about', [PageController::class, 'about'])->name('pages.about');
Route::get('/contact', [PageController::class, 'contact'])->name('pages.contact');
Route::post('/contact', [PageController::class, 'submitContact'])->middleware('throttle:5,1')->name('pages.contact.submit');
Route::get('/return-policy', [PageController::class, 'returnPolicy'])->name('pages.return-policy');
Route::get('/privacy-policy', [PageController::class, 'privacyPolicy'])->name('pages.privacy-policy');
Route::get('/terms', [PageController::class, 'terms'])->name('pages.terms');

// Authenticated Customer Dashboard & Profile (with role-based redirect for Management & Agents)
Route::get('/dashboard', [CustomerDashboardController::class, 'index'])
    ->middleware(['auth', 'two_factor', 'customer.active'])
    ->name('dashboard');

// Authenticated Customer Order History & Tracking (Phase 5 Step 3 Phase B & C)
Route::middleware(['auth', 'two_factor', 'customer.active'])->prefix('customer')->name('customer.')->group(function () {
    Route::get('/orders', [CustomerOrderController::class, 'index'])->name('orders.index');
    Route::get('/orders/{orderNumber}', [CustomerOrderController::class, 'show'])->name('orders.show');
    Route::get('/orders/{orderNumber}/invoice', [CustomerOrderController::class, 'downloadInvoice'])->name('orders.invoice');
    Route::post('/orders/{orderNumber}/cancel', [CustomerOrderController::class, 'cancel'])->name('orders.cancel');

    // Customer Address Book (Phase 5 Step 3 Phase D)
    Route::get('/addresses', [CustomerAddressController::class, 'index'])->name('addresses.index');
    Route::post('/addresses', [CustomerAddressController::class, 'store'])->name('addresses.store');
    Route::put('/addresses/{address}', [CustomerAddressController::class, 'update'])->name('addresses.update');
    Route::delete('/addresses/{address}', [CustomerAddressController::class, 'destroy'])->name('addresses.destroy');
    Route::post('/addresses/{address}/set-default', [CustomerAddressController::class, 'setDefault'])->name('addresses.set-default');

    // Customer Recently Viewed Products (FV-03-04)
    Route::get('/recently-viewed', [RecentlyViewedProductController::class, 'index'])->name('recently-viewed.index');
    Route::post('/recently-viewed', [RecentlyViewedProductController::class, 'store'])->name('recently-viewed.store');
    Route::post('/recently-viewed/merge', [RecentlyViewedProductController::class, 'merge'])->name('recently-viewed.merge');
});

Route::middleware(['auth', 'two_factor', 'customer.active'])->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

// ==========================================
// PUBLIC AGENT REGISTRATION (PHASE 4)
// ==========================================
Route::get('/agent/register', [AgentRegisterController::class, 'create'])->name('agent.register');
Route::post('/agent/register', [AgentRegisterController::class, 'store'])->name('agent.register.store');

// ==========================================
// AGENT PORTAL ROUTES (PHASE 4)
// ==========================================
Route::middleware(['auth', 'role:agent'])->prefix('agent')->name('agent.')->group(function () {
    Route::get('/dashboard', [AgentDashboardController::class, 'index'])->name('dashboard');
    Route::get('/profile', [AgentProfileController::class, 'show'])->name('profile');
    Route::put('/profile', [AgentProfileController::class, 'update'])->name('profile.update');
    Route::get('/team', [AgentTeamController::class, 'index'])->name('team');
    Route::get('/team-index', [AgentTeamController::class, 'index'])->name('team.index');

    // Agent Invitations (HLA recruits LLA)
    Route::get('/invitations', [AgentInvitationController::class, 'index'])->name('invitations.index');
    Route::post('/invitations', [AgentInvitationController::class, 'store'])->name('invitations.store');
    Route::post('/invitations/{invitation}/revoke', [AgentInvitationController::class, 'revoke'])->name('invitations.revoke');

    // Agent Orders placed on behalf of customers
    Route::get('/orders', [AgentOrderController::class, 'index'])->name('orders.index');
    Route::get('/orders/create', [AgentOrderController::class, 'create'])->name('orders.create');
    Route::post('/orders', [AgentOrderController::class, 'store'])->name('orders.store');

    // Agent Commissions (Phase 5)
    Route::get('/commissions', [AgentCommissionController::class, 'index'])->name('commissions.index');

    // Agent Wallet, Payouts & Statements (Phase 6)
    Route::get('/wallet', [AgentWalletController::class, 'index'])->name('wallet.index');
    Route::post('/wallet/payout-request', [AgentWalletController::class, 'requestPayout'])->name('wallet.payout-request');
    Route::post('/wallet/payout-requests/{payoutRequest}/cancel', [AgentWalletController::class, 'cancelPayout'])->name('wallet.payout-requests.cancel');
    Route::get('/wallet/payout-requests/{payoutRequest}/receipt', [AgentWalletController::class, 'downloadReceipt'])->name('wallet.payout-requests.receipt');
    Route::get('/wallet/statements', [AgentWalletController::class, 'statements'])->name('wallet.statements');

    // Agent Announcements Feed & Read Tracking (FV-04-04 Phase 1)
    Route::get('/announcements', [AgentAnnouncementPortalController::class, 'index'])->name('announcements.index');
    Route::post('/announcements/{announcement}/read', [AgentAnnouncementPortalController::class, 'markRead'])->name('announcements.read');

    // Agent Performance Analytics (FV-04-01 Phase 1)
    Route::get('/analytics', [AgentAnalyticsPortalController::class, 'index'])->name('analytics.index');
    Route::get('/analytics/export', [AgentAnalyticsPortalController::class, 'export'])->name('analytics.export');

    // Agent Downline Target Management (FV-04-02 Stage 2)
    Route::post('/team/targets', [AgentAnalyticsPortalController::class, 'storeDownlineTarget'])->name('team.targets.store');
    Route::put('/team/targets/{target}', [AgentAnalyticsPortalController::class, 'updateDownlineTarget'])->name('team.targets.update');

    // Agent Achievements & Honors (FV-04-03 Stage 5)
    Route::get('/achievements', [AgentAchievementPortalController::class, 'index'])->name('achievements.index');
    Route::get('/achievements/downline/{member}', [AgentAchievementPortalController::class, 'showDownlineMember'])->name('achievements.downline.show');
});

// ==========================================
// MANAGEMENT & ADMIN ROUTES (PHASE 3, 4, 6 & 7)
// Protected by Auth, Role, and Mandatory Two-Factor Authentication (Phase 7)
// ==========================================
Route::middleware(['auth', 'role:management', 'two_factor'])->prefix('admin')->name('admin.')->group(function () {
    // Admin root redirect to dashboard
    Route::get('/', fn () => redirect()->route('admin.dashboard'));

    // Overview Dashboard
    Route::get('/dashboard', [AdminDashboardController::class, 'index'])->name('dashboard');

    // Business Approval Center (Centralized Hub for Owner)
    Route::get('/approvals', [AdminApprovalCenterController::class, 'index'])
        ->middleware('role:owner')
        ->name('approvals.index');

    // Staff & User Management (Super Admin & Owner)
    Route::middleware('permission:users.view')->group(function () {
        Route::get('/staff', [AdminStaffController::class, 'index'])->name('staff.index');
        Route::post('/staff', [AdminStaffController::class, 'store'])->middleware('permission:users.create')->name('staff.store');
        Route::put('/staff/{user}', [AdminStaffController::class, 'update'])->middleware('permission:users.update')->name('staff.update');
        Route::post('/staff/{user}/status', [AdminStaffController::class, 'toggleStatus'])->middleware('permission:users.suspend')->name('staff.status');
        Route::post('/staff/{user}/sessions', [AdminStaffController::class, 'killSessions'])->middleware('permission:users.sessions.kill')->name('staff.sessions');
        Route::post('/staff/{user}/reset-mfa', [AdminStaffController::class, 'resetMfa'])->middleware('permission:users.mfa.reset')->name('staff.reset-mfa');
    });

    // Custom Roles & Permissions (Super Admin & Owner)
    Route::middleware('permission:roles.view')->group(function () {
        Route::get('/roles', [AdminRoleController::class, 'index'])->name('roles.index');
        Route::post('/roles', [AdminRoleController::class, 'store'])->middleware('permission:roles.create')->name('roles.store');
        Route::put('/roles/{role}', [AdminRoleController::class, 'update'])->middleware('permission:roles.update')->name('roles.update');
        Route::delete('/roles/{role}', [AdminRoleController::class, 'destroy'])->middleware('permission:roles.delete')->name('roles.destroy');

        // Permission Change Requests (Decision 2 - Owner Approval Workflow)
        Route::get('/roles/permission-requests', [AdminPermissionRequestController::class, 'index'])->name('roles.permission-requests.index');
        Route::post('/roles/permission-requests', [AdminPermissionRequestController::class, 'store'])->name('roles.permission-requests.store');
        Route::post('/roles/permission-requests/{permissionRequest}/approve', [AdminPermissionRequestController::class, 'approve'])
            ->middleware('role:owner') // Owner authority required!
            ->name('roles.permission-requests.approve');
        Route::post('/roles/permission-requests/{permissionRequest}/reject', [AdminPermissionRequestController::class, 'reject'])
            ->middleware('role:owner') // Owner authority required!
            ->name('roles.permission-requests.reject');
    });

    // Business & Security Audit Logs (Owner, Super Admin, Authorized Staff)
    Route::get('/system/logs', [AdminSystemHealthController::class, 'logs'])->name('system.logs');

    // System Health, Diagnostics & Maintenance (Super Admin & Owner)
    Route::middleware('permission:system.health.view')->group(function () {
        Route::get('/system/health', [AdminSystemHealthController::class, 'index'])->name('system.health');
        Route::post('/system/maintenance', [AdminSystemHealthController::class, 'toggleMaintenance'])
            ->middleware('permission:system.maintenance.manage')
            ->name('system.maintenance');
        Route::post('/system/cache/clear', [AdminSystemHealthController::class, 'clearCache'])
            ->middleware('permission:system.maintenance.manage')
            ->name('system.cache.clear');
    });

    // Category Management (Owner, IT Manager, Marketing Manager)
    Route::middleware('role:owner,it_manager,marketing_manager')->group(function () {
        Route::get('/categories', [AdminCategoryController::class, 'index'])->name('categories.index');
        Route::post('/categories', [AdminCategoryController::class, 'store'])->name('categories.store');
        Route::put('/categories/{category}', [AdminCategoryController::class, 'update'])->name('categories.update');
        Route::delete('/categories/{category}', [AdminCategoryController::class, 'destroy'])->name('categories.destroy');
    });

    // Product Management (Owner, IT Manager, Marketing Manager)
    Route::middleware('role:owner,it_manager,marketing_manager')->group(function () {
        Route::get('/products', [AdminProductController::class, 'index'])->name('products.index');
        Route::get('/products/create', [AdminProductController::class, 'create'])->name('products.create');
        Route::post('/products', [AdminProductController::class, 'store'])->name('products.store');
        Route::get('/products/{product}/edit', [AdminProductController::class, 'edit'])->name('products.edit');
        Route::put('/products/{product}', [AdminProductController::class, 'update'])->name('products.update');

        // Product Images
        Route::post('/products/{product}/images', [AdminProductController::class, 'uploadImage'])->name('products.images.upload');
        Route::patch('/products/{product}/images/{image}/primary', [AdminProductController::class, 'setPrimaryImage'])->name('products.images.primary');
        Route::delete('/products/{product}/images/{image}', [AdminProductController::class, 'deleteImage'])->name('products.images.delete');
    });

    // Storefront Banner Management (Owner, IT Manager, Super Admin, Marketing Manager)
    Route::middleware('role:owner,it_manager,super_admin,marketing_manager')->group(function () {
        Route::get('/banners', [AdminBannerController::class, 'index'])->name('banners.index');
        Route::post('/banners', [AdminBannerController::class, 'store'])->name('banners.store');
        Route::put('/banners/{banner}', [AdminBannerController::class, 'update'])->name('banners.update');
        Route::patch('/banners/{banner}/toggle', [AdminBannerController::class, 'toggle'])->name('banners.toggle');
        Route::delete('/banners/{banner}', [AdminBannerController::class, 'destroy'])->name('banners.destroy');
        Route::post('/banners/experience', [AdminBannerController::class, 'updateExperience'])->name('banners.experience.update');
        Route::post('/banners/whatsapp', [AdminBannerController::class, 'updateWhatsApp'])->name('banners.whatsapp.update');
    });

    // Marketing & Coupon Management
    Route::get('/coupons', [AdminCouponController::class, 'index'])->name('coupons.index');
    Route::get('/coupons/create', [AdminCouponController::class, 'create'])->name('coupons.create');
    Route::post('/coupons', [AdminCouponController::class, 'store'])->name('coupons.store');
    Route::get('/coupons/{coupon}', [AdminCouponController::class, 'show'])->name('coupons.show');
    Route::get('/coupons/{coupon}/edit', [AdminCouponController::class, 'edit'])->name('coupons.edit');
    Route::put('/coupons/{coupon}', [AdminCouponController::class, 'update'])->name('coupons.update');
    Route::patch('/coupons/{coupon}/toggle', [AdminCouponController::class, 'toggle'])->name('coupons.toggle');
    Route::delete('/coupons/{coupon}', [AdminCouponController::class, 'destroy'])->name('coupons.destroy');

    // Product Reviews Moderation
    Route::get('/reviews', [AdminProductReviewController::class, 'index'])->name('reviews.index');
    Route::get('/reviews/{review}', [AdminProductReviewController::class, 'show'])->name('reviews.show');
    Route::patch('/reviews/{review}/approve', [AdminProductReviewController::class, 'approve'])->name('reviews.approve');
    Route::patch('/reviews/{review}/reject', [AdminProductReviewController::class, 'reject'])->name('reviews.reject');
    Route::delete('/reviews/{review}', [AdminProductReviewController::class, 'destroy'])->name('reviews.destroy');

    // Inventory Management
    Route::get('/inventory', [AdminInventoryController::class, 'index'])->name('inventory.index');
    Route::get('/inventory/movements', [AdminInventoryController::class, 'movements'])->name('inventory.movements');
    Route::post('/inventory/adjust', [AdminInventoryController::class, 'adjust'])
        ->middleware('role:owner,it_manager,super_admin,product_inventory_manager')
        ->name('inventory.adjust');
    Route::get('/inventory/alerts', [AdminStockAlertController::class, 'index'])->name('inventory.alerts.index');
    Route::post('/inventory/alerts/{stockAlert}/acknowledge', [AdminStockAlertController::class, 'acknowledge'])->name('inventory.alerts.acknowledge');

    // Supplier Management
    Route::get('/suppliers', [AdminSupplierController::class, 'index'])->name('suppliers.index');
    Route::get('/suppliers/create', [AdminSupplierController::class, 'create'])->name('suppliers.create');
    Route::post('/suppliers', [AdminSupplierController::class, 'store'])->name('suppliers.store');
    Route::get('/suppliers/{supplier}', [AdminSupplierController::class, 'show'])->name('suppliers.show');
    Route::get('/suppliers/{supplier}/edit', [AdminSupplierController::class, 'edit'])->name('suppliers.edit');
    Route::put('/suppliers/{supplier}', [AdminSupplierController::class, 'update'])->name('suppliers.update');
    Route::patch('/suppliers/{supplier}/toggle-status', [AdminSupplierController::class, 'toggleStatus'])->name('suppliers.toggle-status');
    Route::post('/suppliers/{supplier}/products', [AdminSupplierController::class, 'linkProduct'])->name('suppliers.products.link');
    Route::put('/suppliers/{supplier}/products/{product}', [AdminSupplierController::class, 'updateProduct'])->name('suppliers.products.update');
    Route::patch('/suppliers/{supplier}/products/{product}/preferred', [AdminSupplierController::class, 'setPreferredProduct'])->name('suppliers.products.preferred');
    Route::delete('/suppliers/{supplier}/products/{product}', [AdminSupplierController::class, 'unlinkProduct'])->name('suppliers.products.unlink');

    // Purchase Order Management
    Route::get('/purchase-orders', [AdminPurchaseOrderController::class, 'index'])->name('purchase-orders.index');
    Route::get('/purchase-orders/create', [AdminPurchaseOrderController::class, 'create'])->name('purchase-orders.create');
    Route::post('/purchase-orders', [AdminPurchaseOrderController::class, 'store'])->name('purchase-orders.store');
    Route::get('/purchase-orders/{purchaseOrder}', [AdminPurchaseOrderController::class, 'show'])->name('purchase-orders.show');
    Route::get('/purchase-orders/{purchaseOrder}/edit', [AdminPurchaseOrderController::class, 'edit'])->name('purchase-orders.edit');
    Route::put('/purchase-orders/{purchaseOrder}', [AdminPurchaseOrderController::class, 'update'])->name('purchase-orders.update');
    Route::post('/purchase-orders/{purchaseOrder}/approve', [AdminPurchaseOrderController::class, 'approve'])->name('purchase-orders.approve');
    Route::post('/purchase-orders/{purchaseOrder}/receive', [AdminPurchaseOrderController::class, 'receive'])->name('purchase-orders.receive');
    Route::post('/purchase-orders/{purchaseOrder}/cancel', [AdminPurchaseOrderController::class, 'cancel'])->name('purchase-orders.cancel');

    // Order Administration
    Route::get('/orders', [AdminOrderController::class, 'index'])->name('orders.index');
    Route::get('/orders/{order}', [AdminOrderController::class, 'show'])->name('orders.show');
    Route::get('/orders/{order}/invoice', [AdminOrderController::class, 'downloadInvoice'])
        ->middleware('permission:orders.view')
        ->name('orders.invoice');
    Route::post('/orders/{order}/status', [AdminOrderController::class, 'updateStatus'])
        ->middleware('role:owner,it_manager,super_admin,operations_manager,accountant')
        ->name('orders.status');
    Route::post('/orders/{order}/courier', [AdminOrderController::class, 'assignCourier'])
        ->middleware('role:owner,it_manager,super_admin,operations_manager,accountant')
        ->name('orders.courier');
    Route::post('/orders/{order}/courier-fee', [AdminOrderController::class, 'updateCourierFee'])
        ->middleware('role:owner,it_manager,super_admin,operations_manager,accountant')
        ->name('orders.courier-fee');
    Route::post('/orders/{order}/reconcile-cod', [AdminOrderController::class, 'reconcileCod'])
        ->middleware('role:owner,it_manager,super_admin,accountant')
        ->name('orders.reconcile-cod');

    // Courier Management
    Route::middleware('role:owner,it_manager,super_admin,operations_manager,product_inventory_manager')->group(function () {
        Route::get('/couriers', [AdminCourierController::class, 'index'])->name('couriers.index');
        Route::post('/couriers', [AdminCourierController::class, 'store'])->name('couriers.store');
        Route::put('/couriers/{courier}', [AdminCourierController::class, 'update'])->name('couriers.update');
    });

    // Customer Directory (Phase 5 Step 3 Phase E)
    Route::middleware('role:owner,it_manager,super_admin,operations_manager,marketing_manager')->group(function () {
        Route::get('/customers', [AdminCustomerController::class, 'index'])->name('customers.index');
        Route::get('/customers/{customer}', [AdminCustomerController::class, 'show'])->name('customers.show');
    });
    Route::post('/customers/{customer}/status', [AdminCustomerController::class, 'toggleStatus'])
        ->middleware('role:owner,it_manager,super_admin,operations_manager')
        ->name('customers.status');

    // Customer Inquiries Management (Phase 5 Step 5 TSK-CORE-02 Step 3)
    Route::middleware('permission:inquiries.manage')->group(function () {
        Route::get('/inquiries', [AdminCustomerInquiryController::class, 'index'])->name('inquiries.index');
        Route::get('/inquiries/{inquiry}', [AdminCustomerInquiryController::class, 'show'])->name('inquiries.show');
        Route::patch('/inquiries/{inquiry}/status', [AdminCustomerInquiryController::class, 'updateStatus'])->name('inquiries.status.update');
        Route::patch('/inquiries/{inquiry}/priority', [AdminCustomerInquiryController::class, 'updatePriority'])->name('inquiries.priority.update');
        Route::patch('/inquiries/{inquiry}/assign', [AdminCustomerInquiryController::class, 'assignStaff'])->name('inquiries.assign');
        Route::post('/inquiries/{inquiry}/notes', [AdminCustomerInquiryController::class, 'storeNote'])->name('inquiries.notes.store');
    });

    // Agent Announcements Broadcast System (FV-04-04 Phase 1)
    Route::prefix('agents/announcements')->name('agents.announcements.')->group(function () {
        Route::get('/', [AdminAgentAnnouncementController::class, 'index'])->middleware('permission:agents.announcements.view')->name('index');
        Route::get('/create', [AdminAgentAnnouncementController::class, 'create'])->middleware('permission:agents.announcements.manage')->name('create');
        Route::post('/', [AdminAgentAnnouncementController::class, 'store'])->middleware('permission:agents.announcements.manage')->name('store');
        Route::get('/{announcement}/edit', [AdminAgentAnnouncementController::class, 'edit'])->middleware('permission:agents.announcements.manage')->name('edit');
        Route::put('/{announcement}', [AdminAgentAnnouncementController::class, 'update'])->middleware('permission:agents.announcements.manage')->name('update');
        Route::post('/{announcement}/publish', [AdminAgentAnnouncementController::class, 'publish'])->middleware('permission:agents.announcements.publish')->name('publish');
        Route::post('/{announcement}/schedule', [AdminAgentAnnouncementController::class, 'schedule'])->middleware('permission:agents.announcements.publish')->name('schedule');
        Route::post('/{announcement}/cancel-schedule', [AdminAgentAnnouncementController::class, 'cancelSchedule'])->middleware('permission:agents.announcements.manage')->name('cancel-schedule');
        Route::post('/{announcement}/archive', [AdminAgentAnnouncementController::class, 'archive'])->middleware('permission:agents.announcements.manage')->name('archive');
        Route::delete('/{announcement}', [AdminAgentAnnouncementController::class, 'destroy'])->middleware('permission:agents.announcements.manage')->name('destroy');
        Route::get('/{announcement}/reads', [AdminAgentAnnouncementController::class, 'reads'])->middleware('permission:agents.announcements.view')->name('reads');
    });

    // Agent Network, Invitations & Dedicated KYC Queue
    Route::get('/agents', [AdminAgentController::class, 'index'])->name('agents.index');
    Route::get('/agents/invitations', [AdminAgentInvitationController::class, 'index'])->name('agents.invitations.index');
    Route::post('/agents/invitations', [AdminAgentInvitationController::class, 'store'])
        ->middleware('role:owner,it_manager,super_admin,agent_manager')
        ->name('agents.invitations.store');
    Route::post('/agents/invitations/{invitation}/revoke', [AdminAgentInvitationController::class, 'revoke'])
        ->middleware('role:owner,it_manager,super_admin,agent_manager')
        ->name('agents.invitations.revoke');

    // Dedicated KYC Queue (placed before {agent} parameter wildcard)
    Route::get('/agents/kyc-queue', [AdminAgentController::class, 'kycQueue'])
        ->middleware('role:owner,it_manager,super_admin,agent_manager,accountant')
        ->name('agents.kyc.queue');
    Route::get('/agents/kyc', [AdminAgentController::class, 'kycQueue'])
        ->middleware('role:owner,it_manager,super_admin,agent_manager,accountant')
        ->name('agents.kyc.index');

    // Dedicated Agent Hierarchy Network & Requests (placed before {agent} parameter wildcard)
    Route::get('/agents/hierarchy', [AdminAgentController::class, 'hierarchyTree'])
        ->middleware('role:owner,it_manager,super_admin,agent_manager')
        ->name('agents.hierarchy');
    Route::get('/agents/hierarchy/nodes', [AdminAgentController::class, 'hierarchyNodes'])
        ->middleware('role:owner,it_manager,super_admin,agent_manager')
        ->name('agents.hierarchy.nodes');
    Route::get('/agents/hierarchy-requests', [AdminAgentController::class, 'hierarchyRequests'])
        ->middleware('role:owner,it_manager,super_admin,agent_manager')
        ->name('agents.hierarchy-requests');
    Route::get('/agents/hierarchy/integrity', [HierarchyAuditController::class, 'index'])
        ->name('agents.hierarchy.integrity');
    Route::post('/agents/hierarchy/integrity/scan', [HierarchyAuditController::class, 'scan'])
        ->name('agents.hierarchy.integrity.scan');

    // Performance, Activity & Reports (Agent Management)
    Route::get('/agents/performance', [AdminAgentController::class, 'performance'])
        ->middleware('role:owner,it_manager,super_admin,agent_manager')
        ->name('agents.performance');
    Route::get('/agents/activity', [AdminAgentController::class, 'activity'])
        ->middleware('role:owner,it_manager,super_admin,agent_manager')
        ->name('agents.activity');
    Route::get('/agents/reports', [AdminAgentController::class, 'reports'])
        ->middleware('role:owner,it_manager,super_admin,agent_manager')
        ->name('agents.reports');
    Route::get('/agents/reports/export', [AdminAgentController::class, 'exportReport'])
        ->middleware('role:owner,it_manager,super_admin,agent_manager')
        ->name('agents.reports.export');

    // Agent Performance Analytics & Sales Targets (FV-04-01 Phase 1)
    Route::prefix('agents/analytics')->name('agents.analytics.')->group(function () {
        Route::get('/', [AdminAgentAnalyticsController::class, 'index'])->name('index');
        Route::get('/export', [AdminAgentAnalyticsController::class, 'export'])->name('export');
        Route::post('/targets', [AdminAgentAnalyticsController::class, 'storeTarget'])->name('targets.store');
        Route::put('/targets/{target}', [AdminAgentAnalyticsController::class, 'updateTarget'])->name('targets.update');
        Route::get('/targets/{target}/history', [AdminAgentAnalyticsController::class, 'targetHistory'])->name('targets.history');
        Route::get('/{agent}', [AdminAgentAnalyticsController::class, 'show'])->name('show');
    });

    // Agent Achievements & Honors Management (FV-04-03 Stage 4)
    Route::prefix('agents/achievements')->name('agents.achievements.')->group(function () {
        Route::get('/', [AdminAgentAchievementRequestController::class, 'index'])->name('index');
        Route::post('/requests', [AdminAgentAchievementRequestController::class, 'store'])->name('requests.store');
        Route::post('/requests/{request}/approve', [AdminAgentAchievementRequestController::class, 'approve'])->name('requests.approve');
        Route::post('/requests/{request}/reject', [AdminAgentAchievementRequestController::class, 'reject'])->name('requests.reject');

        Route::get('/awards', [AdminAgentAchievementAwardController::class, 'index'])->name('awards.index');
        Route::post('/awards/direct-grant', [AdminAgentAchievementAwardController::class, 'directGrant'])->name('awards.direct_grant');
        Route::post('/awards/{award}/revoke', [AdminAgentAchievementAwardController::class, 'revoke'])->name('awards.revoke');
        Route::post('/awards/{award}/reinstate', [AdminAgentAchievementAwardController::class, 'reinstate'])->name('awards.reinstate');
    });

    Route::get('/agents/{agent}', [AdminAgentController::class, 'show'])->name('agents.show');
    Route::post('/agents/{agent}/approve', [AdminAgentController::class, 'approve'])
        ->middleware('role:owner,it_manager,super_admin,agent_manager')
        ->name('agents.approve');
    Route::post('/agents/{agent}/reject', [AdminAgentController::class, 'reject'])
        ->middleware('role:owner,it_manager,super_admin,agent_manager')
        ->name('agents.reject');
    Route::post('/agents/{agent}/status', [AdminAgentController::class, 'updateStatus'])
        ->middleware('role:owner,it_manager,super_admin,agent_manager')
        ->name('agents.status');
    Route::post('/agents/{agent}/status-update', [AdminAgentController::class, 'updateStatus'])
        ->middleware('role:owner,it_manager,super_admin,agent_manager')
        ->name('agents.status.update');

    // Dedicated KYC Queue Actions (unmask PII and documents)
    Route::post('/agents/{agent}/unmask-pii', [AdminAgentController::class, 'unmaskPii'])
        ->middleware('role:owner,it_manager,super_admin,agent_manager,accountant')
        ->name('agents.unmask-pii');
    Route::get('/agents/{agent}/kyc-document', [AdminAgentController::class, 'viewKycDocument'])
        ->middleware('role:owner,it_manager,super_admin,agent_manager,accountant')
        ->name('agents.kyc-document');

    Route::post('/agents/{agent}/kyc', [AdminAgentController::class, 'updateKyc'])
        ->middleware('role:owner,it_manager,super_admin,accountant,agent_manager')
        ->name('agents.kyc');
    Route::post('/agents/{agent}/kyc-update', [AdminAgentController::class, 'updateKyc'])
        ->middleware('role:owner,it_manager,super_admin,accountant,agent_manager')
        ->name('agents.kyc.update');

    Route::post('/agents/{agent}/hierarchy-move-request', [AdminAgentController::class, 'requestHierarchyMove'])
        ->middleware('role:owner,it_manager,super_admin,agent_manager')
        ->name('agents.hierarchy-move-request');
    Route::post('/agents/{agent}/hierarchy-request', [AdminAgentController::class, 'requestHierarchyMove'])
        ->middleware('role:owner,it_manager,super_admin,agent_manager')
        ->name('agents.hierarchy.request');
    Route::post('/agents/hierarchy-requests', [AdminAgentController::class, 'storeHierarchyRequest'])
        ->middleware('role:owner,it_manager,super_admin,agent_manager')
        ->name('agents.hierarchy-requests.store');

    Route::post('/agents/hierarchy-requests/{changeRequest}/review', [AdminAgentController::class, 'reviewHierarchyMove'])
        ->middleware('role:owner') // Owner approval mandatory!
        ->name('agents.hierarchy-move-review');
    Route::post('/agents/hierarchy-requests/{changeRequest}/review-action', [AdminAgentController::class, 'reviewHierarchyMove'])
        ->middleware('role:owner')
        ->name('agents.hierarchy.review');

    // Commission Ledger & Governance (Phase 5)
    Route::get('/commissions/ledger', [AdminCommissionController::class, 'ledger'])->name('commissions.ledger');
    Route::get('/commissions/rules', [AdminCommissionController::class, 'rules'])->name('commissions.rules');
    Route::post('/commissions/rules/request', [AdminCommissionController::class, 'requestRuleChange'])
        ->middleware('role:owner,it_manager')
        ->name('commissions.rules.request');
    Route::post('/commissions/rules/{changeRequest}/review', [AdminCommissionController::class, 'reviewRuleChange'])
        ->middleware('role:owner') // Owner approval mandatory!
        ->name('commissions.rules.review');
    Route::post('/commissions/process-eligible', [AdminCommissionController::class, 'processEligible'])
        ->middleware('role:owner,it_manager,accountant')
        ->name('commissions.process-eligible');

    // Commission Payouts & Finance Governance (Phase 6 - Owner & Accountant only, SEC-06-03)
    Route::middleware('role:owner,accountant')->group(function () {
        Route::get('/payouts/dashboard', [AdminPayoutController::class, 'dashboard'])->name('payouts.dashboard');
        Route::get('/payouts', [AdminPayoutController::class, 'index'])->name('payouts.index');
        Route::get('/payouts/{payoutRequest}', [AdminPayoutController::class, 'show'])->name('payouts.show');
        Route::get('/payouts/{payoutRequest}/receipt', [AdminPayoutController::class, 'downloadReceipt'])->name('payouts.receipt');
        Route::post('/payouts/{payoutRequest}/approve', [AdminPayoutController::class, 'approve'])
            ->middleware('role:owner') // Owner approval mandatory!
            ->name('payouts.approve');
        Route::post('/payouts/{payoutRequest}/reject', [AdminPayoutController::class, 'reject'])
            ->middleware('role:owner') // Owner rejection mandatory!
            ->name('payouts.reject');
        Route::post('/payouts/{payoutRequest}/process', [AdminPayoutController::class, 'process'])
            ->name('payouts.process');
        Route::post('/payouts/{payoutRequest}/fail', [AdminPayoutController::class, 'fail'])
            ->name('payouts.fail');
        Route::post('/payouts/{payoutRequest}/record-payment', [AdminPayoutController::class, 'recordPayment'])
            ->name('payouts.record-payment');
    });

    // Financial Contribution & Operating P&L (Phase 2 - Step 3B: Owner & Accountant ONLY)
    Route::middleware('role:owner,accountant')->group(function () {
        Route::get('/finance/contribution', [AdminFinancialReportController::class, 'index'])->name('finance.contribution');
        Route::get('/finance/contribution/summary', [AdminFinancialReportController::class, 'summary'])->name('finance.contribution.summary');
    });
});

Route::redirect('/system/health', '/admin/system/health');
Route::redirect('/system/logs', '/admin/system/logs');

require __DIR__.'/auth.php';
