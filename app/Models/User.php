<?php

namespace App\Models;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'phone',
        'role',
        'role_id',
        'protected_authority',
        'status',
        'referral_code',
        'parent_id',
        'password',
        'two_factor_secret',
        'two_factor_recovery_codes',
        'two_factor_confirmed_at',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'two_factor_secret',
        'two_factor_recovery_codes',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'two_factor_confirmed_at' => 'datetime',
            'two_factor_secret' => 'encrypted',
            'two_factor_recovery_codes' => 'encrypted:array',
            'password' => 'hashed',
            'status' => UserStatus::class,
        ];
    }

    public function getRoleAttribute($value): UserRole|string
    {
        if (is_null($value)) {
            return UserRole::CUSTOMER;
        }

        return UserRole::tryFrom($value) ?? $value;
    }

    public function setRoleAttribute($value): void
    {
        $this->attributes['role'] = $value instanceof UserRole ? $value->value : (string) $value;
    }

    /**
     * Bootstrap the model and its traits with security guardrails.
     */
    protected static function booted(): void
    {
        static::creating(function (User $user) {
            if (empty($user->role_id) && !empty($user->role)) {
                $roleSlug = $user->role instanceof UserRole ? $user->role->value : (string) $user->role;
                $matchedRole = Role::where('name', $roleSlug)->first();
                if ($matchedRole) {
                    $user->role_id = $matchedRole->id;
                }
            }

            // Explicit protected authority assignment on creation
            if (empty($user->protected_authority)) {
                $roleSlug = $user->role instanceof UserRole ? $user->role->value : (string) $user->role;
                if ($roleSlug === 'owner' || $user->role === UserRole::OWNER) {
                    $user->protected_authority = 'business';
                } elseif (in_array($roleSlug, ['super_admin', 'it_manager'], true) || in_array($user->role, [UserRole::SUPER_ADMIN, UserRole::IT_MANAGER], true)) {
                    $user->protected_authority = 'technical';
                }
            }
        });

        static::updating(function (User $user) {
            $actor = auth()->user();

            // 1. BUSINESS AUTHORITY PROTECTION (Owner Account)
            $isBusinessAuthority = $user->getOriginal('protected_authority') === 'business'
                || $user->getOriginal('role') === 'owner'
                || $user->getOriginal('role') === UserRole::OWNER;

            if ($isBusinessAuthority) {
                // If there is an authenticated actor and it is NOT this exact owner user
                if ($actor && $actor->id !== $user->id) {
                    throw new \Illuminate\Auth\Access\AuthorizationException(
                        'The Business Owner account cannot be modified by any other administrator or Super Admin.'
                    );
                }

                // If someone is attempting to change role away from owner or suspend owner
                if ($user->isDirty('role') || $user->isDirty('status') || $user->isDirty('protected_authority')) {
                    $newRoleValue = $user->role instanceof UserRole ? $user->role->value : (string) $user->role;
                    if ($newRoleValue !== 'owner' || $user->status !== UserStatus::ACTIVE || $user->protected_authority !== 'business') {
                        throw new \Illuminate\Auth\Access\AuthorizationException(
                            'The Business Owner role, active status, and business authority are immutable.'
                        );
                    }
                }
            }

            // 2. TECHNICAL AUTHORITY PROTECTION (Super Admin Account)
            $isTechnicalAuthority = $user->getOriginal('protected_authority') === 'technical'
                || $user->getOriginal('role') === 'super_admin'
                || $user->getOriginal('role') === UserRole::SUPER_ADMIN
                || $user->getOriginal('role') === 'it_manager'
                || $user->getOriginal('role') === UserRole::IT_MANAGER;

            if ($isTechnicalAuthority) {
                // If there is an authenticated actor and it is NOT this exact Super Admin user
                if ($actor && $actor->id !== $user->id) {
                    throw new \Illuminate\Auth\Access\AuthorizationException(
                        'The Technical Authority (Super Admin) account cannot be modified by the Business Owner or any other administrator.'
                    );
                }

                // If someone is attempting to change role away from super_admin, suspend, or alter technical authority
                if ($user->isDirty('role') || $user->isDirty('status') || $user->isDirty('protected_authority')) {
                    $newRoleValue = $user->role instanceof UserRole ? $user->role->value : (string) $user->role;
                    if (!in_array($newRoleValue, ['super_admin', 'it_manager'], true) || $user->status !== UserStatus::ACTIVE || $user->protected_authority !== 'technical') {
                        throw new \Illuminate\Auth\Access\AuthorizationException(
                            'The Super Admin role, active status, and technical authority are immutable.'
                        );
                    }
                }
            }
        });

        static::deleting(function (User $user) {
            if ($user->isBusinessAuthority()) {
                throw new \Illuminate\Auth\Access\AuthorizationException(
                    'The Business Owner account cannot be deleted under any circumstances.'
                );
            }

            if ($user->isTechnicalAuthority()) {
                throw new \Illuminate\Auth\Access\AuthorizationException(
                    'The Technical Authority (Super Admin) account cannot be deleted under any circumstances.'
                );
            }

            // Strict Financial Protection: Agents or users with commission earnings, wallet history, or payouts cannot be deleted
            if ($user->commissions()->exists() || $user->originatedCommissions()->exists()) {
                throw new \Illuminate\Auth\Access\AuthorizationException(
                    "Integrity Violation: User '{$user->name}' ({$user->email}) has associated commission ledger records and cannot be deleted."
                );
            }

            if ($user->walletTransactions()->exists()) {
                throw new \Illuminate\Auth\Access\AuthorizationException(
                    "Integrity Violation: User '{$user->name}' ({$user->email}) has wallet transaction history and cannot be deleted."
                );
            }

            if ($user->payoutRequests()->exists()) {
                throw new \Illuminate\Auth\Access\AuthorizationException(
                    "Integrity Violation: User '{$user->name}' ({$user->email}) has commission payout records and cannot be deleted."
                );
            }
        });
    }

    /**
     * Direct sponsor/parent in the agent hierarchy.
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(User::class, 'parent_id');
    }

    /**
     * Direct children/downline agents.
     */
    public function children(): HasMany
    {
        return $this->hasMany(User::class, 'parent_id');
    }

    /**
     * Agent profile details (KYC, bank details, wallet).
     */
    public function agentProfile(): HasOne
    {
        return $this->hasOne(AgentProfile::class);
    }

    /**
     * Orders placed by this agent on behalf of customers.
     */
    public function placedOrders(): HasMany
    {
        return $this->hasMany(Order::class, 'agent_id');
    }

    /**
     * Orders credited to this agent via their referral code.
     */
    public function referralOrders(): HasMany
    {
        return $this->hasMany(Order::class, 'referral_code', 'referral_code');
    }

    /**
     * Commission ledger entries where this user is the beneficiary.
     */
    public function commissions(): HasMany
    {
        return $this->hasMany(CommissionLedgerEntry::class, 'agent_id');
    }

    /**
     * Commission ledger entries originated from sales by this agent.
     */
    public function originatedCommissions(): HasMany
    {
        return $this->hasMany(CommissionLedgerEntry::class, 'selling_agent_id');
    }

    public function payoutRequests(): HasMany
    {
        return $this->hasMany(PayoutRequest::class, 'agent_id');
    }

    public function walletTransactions(): HasMany
    {
        return $this->hasMany(WalletTransaction::class, 'agent_id');
    }

    /**
     * Generate a unique uppercase alphanumeric referral code.
     */
    public static function generateUniqueReferralCode(string $seed = 'AGT'): string
    {
        $prefix = strtoupper(substr(preg_replace('/[^a-zA-Z]/', '', $seed), 0, 3));
        if (strlen($prefix) < 3) {
            $prefix = 'AGT';
        }

        do {
            $random = strtoupper(\Illuminate\Support\Str::random(5));
            $code = "{$prefix}-{$random}";
        } while (static::where('referral_code', $code)->exists());

        return $code;
    }

    /**
     * All ancestors in the hierarchy closure (parents, grandparents, etc.).
     */
    public function ancestors(): BelongsToMany
    {
        return $this->belongsToMany(
            User::class,
            'user_closures',
            'descendant_id',
            'ancestor_id'
        )->withPivot('depth')->wherePivot('depth', '>', 0);
    }

    /**
     * All descendants in the hierarchy closure (children, grandchildren, etc.).
     */
    public function descendants(): BelongsToMany
    {
        return $this->belongsToMany(
            User::class,
            'user_closures',
            'ancestor_id',
            'descendant_id'
        )->withPivot('depth')->wherePivot('depth', '>', 0);
    }

    /**
     * Role check helpers.
     */
    public function isOwner(): bool
    {
        return $this->role === UserRole::OWNER || $this->protected_authority === 'business';
    }

    public function isSuperAdmin(): bool
    {
        return $this->isTechnicalAuthority();
    }

    public function isProtectedAuthority(): bool
    {
        return !empty($this->protected_authority);
    }

    public function isBusinessAuthority(): bool
    {
        return $this->protected_authority === 'business' || $this->role === UserRole::OWNER || $this->getRoleValue() === 'owner';
    }

    public function isTechnicalAuthority(): bool
    {
        return $this->protected_authority === 'technical'
            || $this->role === UserRole::SUPER_ADMIN
            || $this->role === UserRole::IT_MANAGER
            || in_array($this->getRoleValue(), ['super_admin', 'it_manager'], true);
    }

    public function roleModel(): BelongsTo
    {
        return $this->belongsTo(Role::class, 'role_id');
    }

    public function assignedRoles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'role_user');
    }

    public function getEffectiveRoleModel(): ?Role
    {
        if ($this->relationLoaded('roleModel') && $this->roleModel) {
            return $this->roleModel;
        }

        if ($this->role_id) {
            return $this->roleModel()->first();
        }

        $roleSlug = $this->role instanceof UserRole ? $this->role->value : (string) $this->role;
        return Role::where('name', $roleSlug)->first();
    }

    public function hasPermissionTo(string $permissionName): bool
    {
        if ($this->isOwner()) {
            return true;
        }

        $role = $this->getEffectiveRoleModel();
        if ($role && $role->hasPermissionTo($permissionName)) {
            return true;
        }

        return $this->assignedRoles->contains(fn ($r) => $r->hasPermissionTo($permissionName));
    }

    public function getPermissionsList(): array
    {
        if ($this->isOwner()) {
            return Permission::pluck('name')->all();
        }

        $perms = collect();
        $role = $this->getEffectiveRoleModel();
        if ($role) {
            $perms = $perms->merge($role->permissions->pluck('name'));
        }
        foreach ($this->assignedRoles as $r) {
            $perms = $perms->merge($r->permissions->pluck('name'));
        }

        return $perms->unique()->values()->all();
    }

    public function syncRole(string|Role $role): void
    {
        if (is_string($role)) {
            $roleObj = Role::where('name', $role)->first();
            $roleName = $role;
            $roleId = $roleObj?->id;
        } else {
            $roleObj = $role;
            $roleName = $role->name;
            $roleId = $role->id;
        }

        $this->role = UserRole::tryFrom($roleName) ?? $roleName;
        $this->role_id = $roleId;
        $this->save();

        if ($roleId) {
            $this->assignedRoles()->sync([$roleId]);
        }
    }

    public function getRoleValue(): string
    {
        return $this->role instanceof UserRole ? $this->role->value : (string) $this->role;
    }

    public function isManagement(): bool
    {
        if ($this->role instanceof UserRole) {
            return $this->role->isManagement();
        }

        $nonStaff = ['customer', 'high_level_agent', 'low_level_agent'];
        return !in_array($this->getRoleValue(), $nonStaff, true);
    }

    public function isAgent(): bool
    {
        if ($this->role instanceof UserRole) {
            return $this->role->isAgent();
        }

        return in_array($this->getRoleValue(), ['high_level_agent', 'low_level_agent'], true);
    }

    public function isAgentManager(): bool
    {
        return $this->role === UserRole::AGENT_MANAGER || $this->getRoleValue() === 'agent_manager';
    }

    public function isProductInventoryManager(): bool
    {
        return $this->role === UserRole::PRODUCT_INVENTORY_MANAGER || $this->getRoleValue() === 'product_inventory_manager';
    }

    public function isOperationsManager(): bool
    {
        return $this->role === UserRole::OPERATIONS_MANAGER || $this->getRoleValue() === 'operations_manager';
    }

    public function isAccountant(): bool
    {
        return $this->role === UserRole::ACCOUNTANT || $this->getRoleValue() === 'accountant';
    }

    public function createdInvitations(): HasMany
    {
        return $this->hasMany(AgentInvitation::class, 'created_by');
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class, 'customer_id');
    }

    public function customerAddresses(): HasMany
    {
        return $this->hasMany(CustomerAddress::class, 'user_id');
    }

    public function defaultAddress(): HasOne
    {
        return $this->hasOne(CustomerAddress::class, 'user_id')->where('is_default', true);
    }

    public function isHighLevelAgent(): bool
    {
        return $this->role === UserRole::HIGH_LEVEL_AGENT || $this->getRoleValue() === 'high_level_agent';
    }

    public function isLowLevelAgent(): bool
    {
        return $this->role === UserRole::LOW_LEVEL_AGENT || $this->getRoleValue() === 'low_level_agent';
    }

    public function isCustomer(): bool
    {
        if ($this->role instanceof UserRole) {
            return $this->role->isCustomer();
        }

        return $this->getRoleValue() === 'customer';
    }

    public function isActive(): bool
    {
        return $this->status === UserStatus::ACTIVE;
    }

    /**
     * Check if user has confirmed Two-Factor Authentication.
     */
    public function hasTwoFactorEnabled(): bool
    {
        return !empty($this->two_factor_secret) && $this->two_factor_confirmed_at !== null;
    }
}
