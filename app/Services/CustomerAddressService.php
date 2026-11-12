<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\CustomerAddress;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CustomerAddressService
{
    /**
     * Maximum number of saved addresses allowed per customer.
     */
    public const MAX_ADDRESSES = 10;

    /**
     * Create a new delivery address for the customer with atomic user-row locking.
     * Enforces the maximum 10-address limit and default-address invariant.
     *
     * @throws ValidationException
     */
    public function createAddress(User $user, array $data): CustomerAddress
    {
        return DB::transaction(function () use ($user, $data) {
            // Concurrency serialization: Acquire pessimistic row-lock on owning user
            $lockedUser = User::where('id', $user->id)->lockForUpdate()->first();
            if (!$lockedUser) {
                throw new \InvalidArgumentException('Customer user record not found.');
            }

            // Authoritative transaction-level capacity check
            $existingCount = $lockedUser->customerAddresses()->count();
            if ($existingCount >= self::MAX_ADDRESSES) {
                throw ValidationException::withMessages([
                    'address_limit' => 'You have reached the maximum limit of ' . self::MAX_ADDRESSES . ' saved delivery addresses. Please update or remove an existing address.',
                ]);
            }

            // Default invariant: First address is always default; otherwise demote if marked default
            if ($existingCount === 0) {
                $data['is_default'] = true;
            } elseif (!empty($data['is_default'])) {
                $lockedUser->customerAddresses()->where('is_default', true)->update(['is_default' => false]);
                $data['is_default'] = true;
            } else {
                $data['is_default'] = false;
            }

            $data['user_id'] = $lockedUser->id;
            $address = CustomerAddress::create($data);

            AuditLog::log('customer_address_created', $address, null, $address->toArray());

            return $address;
        });
    }

    /**
     * Update an existing delivery address with atomic user-row locking.
     * Enforces strict ownership and preserves the default-address invariant:
     * exactly one default exists whenever the customer has at least one address.
     *
     * @throws ValidationException
     */
    public function updateAddress(User $user, int $addressId, array $data): CustomerAddress
    {
        return DB::transaction(function () use ($user, $addressId, $data) {
            // Concurrency serialization: Acquire pessimistic row-lock on owning user
            $lockedUser = User::where('id', $user->id)->lockForUpdate()->first();
            if (!$lockedUser) {
                throw new \InvalidArgumentException('Customer user record not found.');
            }

            // Strict IDOR defense: resolve address scoped to locked user
            $address = $lockedUser->customerAddresses()->where('id', $addressId)->lockForUpdate()->firstOrFail();
            $oldValues = $address->toArray();

            // Default address invariant handling
            if (!empty($data['is_default'])) {
                // Setting this address as default: Demote all other addresses
                $lockedUser->customerAddresses()
                    ->where('id', '!=', $address->id)
                    ->where('is_default', true)
                    ->update(['is_default' => false]);
                $data['is_default'] = true;
            } else {
                // Unchecking default or leaving as non-default
                if ($address->is_default) {
                    // Invariant Guard: Customer cannot leave zero defaults while addresses exist.
                    // Automatically promote the most recently updated eligible address to default.
                    $replacement = $lockedUser->customerAddresses()
                        ->where('id', '!=', $address->id)
                        ->latest('updated_at')
                        ->first();

                    if ($replacement) {
                        $replacement->update(['is_default' => true]);
                        $data['is_default'] = false;
                    } else {
                        // This is the customer's sole address: it MUST remain default!
                        $data['is_default'] = true;
                    }
                } else {
                    $data['is_default'] = false;
                }
            }

            $address->update($data);

            AuditLog::log('customer_address_updated', $address, $oldValues, $address->fresh()->toArray());

            return $address->fresh();
        });
    }

    /**
     * Remove a saved delivery address with atomic user-row locking.
     * Enforces strict ownership and automatically promotes a remaining address to default
     * if the deleted address was the active default.
     */
    public function deleteAddress(User $user, int $addressId): void
    {
        DB::transaction(function () use ($user, $addressId) {
            // Concurrency serialization: Acquire pessimistic row-lock on owning user
            $lockedUser = User::where('id', $user->id)->lockForUpdate()->first();
            if (!$lockedUser) {
                throw new \InvalidArgumentException('Customer user record not found.');
            }

            // Strict IDOR defense: resolve address scoped to locked user
            $address = $lockedUser->customerAddresses()->where('id', $addressId)->lockForUpdate()->firstOrFail();

            $wasDefault = $address->is_default;
            $oldValues = $address->toArray();

            $address->delete();

            // Default Invariant Guard: If default was deleted, promote latest remaining address
            if ($wasDefault) {
                $nextDefault = $lockedUser->customerAddresses()->latest('updated_at')->first();
                if ($nextDefault) {
                    $nextDefault->update(['is_default' => true]);
                }
            }

            AuditLog::log('customer_address_deleted', null, $oldValues, null);
        });
    }

    /**
     * Set a specified address as the customer's default delivery address.
     * Demotes all other addresses atomically under parent user-row lock.
     */
    public function setDefaultAddress(User $user, int $addressId): CustomerAddress
    {
        return DB::transaction(function () use ($user, $addressId) {
            // Concurrency serialization: Acquire pessimistic row-lock on owning user
            $lockedUser = User::where('id', $user->id)->lockForUpdate()->first();
            if (!$lockedUser) {
                throw new \InvalidArgumentException('Customer user record not found.');
            }

            // Strict IDOR defense: resolve address scoped to locked user
            $address = $lockedUser->customerAddresses()->where('id', $addressId)->lockForUpdate()->firstOrFail();

            // Atomically demote existing defaults and promote target
            $lockedUser->customerAddresses()
                ->where('id', '!=', $address->id)
                ->where('is_default', true)
                ->update(['is_default' => false]);

            $address->update(['is_default' => true]);

            AuditLog::log('customer_address_default_changed', $address, ['is_default' => false], ['is_default' => true]);

            return $address->fresh();
        });
    }

    /**
     * Save a new address optionally submitted during checkout.
     * Runs with atomic user-row locking, enforces the 10-address cap,
     * skips duplicate entries, and sets first address as default.
     *
     * Returns the created address or null if cap is reached or address exists.
     */
    public function saveAddressFromCheckout(User $user, array $orderData): ?CustomerAddress
    {
        return DB::transaction(function () use ($user, $orderData) {
            // Concurrency serialization: Acquire pessimistic row-lock on owning user
            $lockedUser = User::where('id', $user->id)->lockForUpdate()->first();
            if (!$lockedUser) {
                return null;
            }

            // Authoritative transaction-level capacity check: Silently skip if at or above cap
            $existingCount = $lockedUser->customerAddresses()->count();
            if ($existingCount >= self::MAX_ADDRESSES) {
                return null;
            }

            // Duplicate detection within customer's address book
            $alreadyExists = $lockedUser->customerAddresses()
                ->where('address_line_1', $orderData['shipping_address'])
                ->where('city', $orderData['city'])
                ->where('district', $orderData['district'])
                ->exists();

            if ($alreadyExists) {
                return null;
            }

            $isFirst = ($existingCount === 0);

            $address = CustomerAddress::create([
                'user_id' => $lockedUser->id,
                'label' => 'Delivery Address',
                'recipient_name' => $orderData['customer_name'],
                'phone' => $orderData['customer_phone'],
                'address_line_1' => $orderData['shipping_address'],
                'city' => $orderData['city'],
                'district' => $orderData['district'],
                'postal_code' => $orderData['postal_code'] ?? null,
                'delivery_instructions' => $orderData['notes'] ?? null,
                'is_default' => $isFirst,
            ]);

            AuditLog::log('customer_address_created', $address, null, $address->toArray());

            return $address;
        });
    }
}

