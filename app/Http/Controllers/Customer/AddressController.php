<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Public\CheckoutController;
use App\Http\Requests\CustomerAddressRequest;
use App\Services\CustomerAddressService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AddressController extends Controller
{
    public function __construct(
        protected CustomerAddressService $addressService
    ) {}

    /**
     * Display the customer's saved address book.
     */
    public function index(Request $request): Response|RedirectResponse
    {
        $user = $request->user();

        if (!$user) {
            return redirect()->route('login');
        }

        // Server-side redirection for non-customer management or agent roles
        if ($user->isOwner() || $user->isManagement()) {
            return redirect()->route('admin.dashboard');
        }

        if ($user->isAgent()) {
            return redirect()->route('agent.dashboard');
        }

        $addresses = $user->customerAddresses()
            ->orderByDesc('is_default')
            ->latest('id')
            ->get();

        return Inertia::render('Customer/Addresses/Index', [
            'addresses' => $addresses,
            'districts' => CheckoutController::SRI_LANKA_DISTRICTS,
            'maxAddresses' => CustomerAddressService::MAX_ADDRESSES,
            'addressCount' => $addresses->count(),
            'customer' => [
                'name' => $user->name,
                'email' => $user->email,
            ],
        ]);
    }

    /**
     * Store a newly created customer delivery address.
     * Delegates to centralized CustomerAddressService for atomic locking and invariant enforcement.
     */
    public function store(CustomerAddressRequest $request): RedirectResponse
    {
        $user = $request->user();

        if ($user->isOwner() || $user->isManagement() || $user->isAgent()) {
            return redirect()->route('dashboard');
        }

        $this->addressService->createAddress($user, $request->validated());

        return redirect()->route('customer.addresses.index')->with('success', 'Delivery address saved successfully.');
    }

    /**
     * Update an existing delivery address.
     * Delegates to centralized CustomerAddressService for IDOR protection and default invariant preservation.
     */
    public function update(CustomerAddressRequest $request, int $id): RedirectResponse
    {
        $user = $request->user();

        $this->addressService->updateAddress($user, $id, $request->validated());

        return redirect()->route('customer.addresses.index')->with('success', 'Delivery address updated successfully.');
    }

    /**
     * Remove a saved delivery address.
     * Delegates to centralized CustomerAddressService for IDOR protection and auto-promotion of default.
     */
    public function destroy(Request $request, int $id): RedirectResponse
    {
        $user = $request->user();

        $this->addressService->deleteAddress($user, $id);

        return redirect()->route('customer.addresses.index')->with('success', 'Delivery address removed successfully.');
    }

    /**
     * Set a specified address as the customer's default delivery address.
     */
    public function setDefault(Request $request, int $id): RedirectResponse
    {
        $user = $request->user();

        $address = $this->addressService->setDefaultAddress($user, $id);

        return redirect()->route('customer.addresses.index')->with('success', "Address '{$address->label}' is now your default delivery address.");
    }
}
