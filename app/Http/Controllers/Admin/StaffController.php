<?php

namespace App\Http\Controllers\Admin;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;

class StaffController extends Controller
{
    /**
     * Display a directory of management staff accounts.
     */
    public function index(Request $request): Response
    {
        $actor = $request->user();

        // Staff includes management roles and any user assigned to staff/custom roles
        $staffRoles = Role::whereNotIn('name', ['customer', 'high_level_agent', 'low_level_agent'])->pluck('id');

        $staff = User::with('roleModel')
            ->where(function ($q) use ($staffRoles) {
                $q->whereIn('role', [
                    UserRole::OWNER,
                    UserRole::SUPER_ADMIN,
                    UserRole::MARKETING_MANAGER,
                    UserRole::ACCOUNTANT,
                    UserRole::PRODUCT_INVENTORY_MANAGER,
                    UserRole::OPERATIONS_MANAGER,
                    UserRole::AGENT_MANAGER,
                    UserRole::IT_MANAGER,
                ])->orWhereIn('role_id', $staffRoles);
            })
            ->orderByRaw("CASE WHEN role = 'owner' THEN 0 WHEN role = 'super_admin' THEN 1 ELSE 2 END")
            ->orderBy('name')
            ->get();

        $availableRoles = Role::whereNotIn('name', ['customer', 'high_level_agent', 'low_level_agent'])
            ->when(!$actor->isOwner(), fn ($q) => $q->where('name', '!=', 'owner'))
            ->when(!$actor->isTechnicalAuthority(), fn ($q) => $q->where('name', '!=', 'super_admin'))
            ->get(['id', 'name', 'display_name', 'is_protected']);

        return Inertia::render('Admin/Staff/Index', [
            'staff' => $staff,
            'availableRoles' => $availableRoles,
            'isOwner' => $actor->isOwner(),
            'isTechnicalAuthority' => $actor->isTechnicalAuthority(),
            'canManageStaff' => $actor->isOwner() || $actor->hasPermissionTo('users.create'),
        ]);
    }

    /**
     * Store a newly created staff member.
     */
    public function store(Request $request): RedirectResponse
    {
        $actor = $request->user();

        if (!$actor->isOwner() && !$actor->hasPermissionTo('users.create')) {
            abort(403, 'Unauthorized to create staff members.');
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:users,email'],
            'phone' => ['required', 'string', 'max:20'],
            'role_id' => ['required', 'exists:roles,id'],
            'password' => ['required', Password::defaults()],
        ]);

        $role = Role::findOrFail($validated['role_id']);

        // Only the Owner can assign the Owner role
        if ($role->name === 'owner' && !$actor->isOwner()) {
            abort(403, 'Security Violation: Only the Owner can create another Owner account.');
        }

        // Only a Technical Authority can assign the Super Admin role
        if ($role->name === 'super_admin' && !$actor->isTechnicalAuthority()) {
            abort(403, 'Security Violation: Only a Technical Authority (Super Admin) can create another Super Admin account.');
        }

        $protectedAuthority = null;
        if ($role->name === 'owner') {
            $protectedAuthority = 'business';
        } elseif (in_array($role->name, ['super_admin', 'it_manager'], true)) {
            $protectedAuthority = 'technical';
        }

        $staffUser = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'phone' => $validated['phone'],
            'role' => UserRole::tryFrom($role->name) ?? $role->name,
            'role_id' => $role->id,
            'protected_authority' => $protectedAuthority,
            'status' => UserStatus::ACTIVE,
            'password' => Hash::make($validated['password']),
            'email_verified_at' => now(),
        ]);

        $staffUser->assignedRoles()->sync([$role->id]);

        AuditLog::log('staff_created', $staffUser, null, [
            'name' => $staffUser->name,
            'email' => $staffUser->email,
            'role' => $role->name,
            'created_by' => $actor->name,
        ]);

        return redirect()->route('admin.staff.index')->with(
            'success',
            "Staff member '{$staffUser->name}' created successfully as {$role->display_name}."
        );
    }

    /**
     * Update an existing staff member's details and assigned role.
     */
    public function update(Request $request, User $user): RedirectResponse
    {
        $actor = $request->user();

        // OWNER & TECHNICAL AUTHORITY PROTECTION: Protected accounts can only be edited by themselves
        if ($user->isBusinessAuthority() && $actor->id !== $user->id) {
            abort(403, 'Security Violation: The Business Owner account cannot be modified by any other administrator.');
        }

        if ($user->isTechnicalAuthority() && $actor->id !== $user->id) {
            abort(403, 'Security Violation: The Super Admin account is protected from Business Owner and external administrative modification.');
        }

        if (!$actor->isOwner() && !$actor->isTechnicalAuthority() && !$actor->hasPermissionTo('users.update')) {
            abort(403, 'Unauthorized to update staff members.');
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', Rule::unique('users')->ignore($user->id)],
            'phone' => ['required', 'string', 'max:20'],
            'role_id' => ['required', 'exists:roles,id'],
            'password' => ['nullable', Password::defaults()],
        ]);

        $newRole = Role::findOrFail($validated['role_id']);

        // PROTECTION: Cannot demote protected accounts
        if ($user->isBusinessAuthority() && $newRole->name !== 'owner') {
            abort(403, 'Security Violation: The Business Owner role cannot be changed.');
        }

        if ($user->isTechnicalAuthority() && $newRole->name !== 'super_admin') {
            abort(403, 'Security Violation: The Super Admin role cannot be changed.');
        }

        // Cross-role assignment restrictions
        if ($newRole->name === 'owner' && !$actor->isOwner()) {
            abort(403, 'Security Violation: Only the Owner can assign the Owner role.');
        }

        if ($newRole->name === 'super_admin' && !$actor->isTechnicalAuthority()) {
            abort(403, 'Security Violation: Only a Technical Authority (Super Admin) can assign the Super Admin role.');
        }

        $oldRole = $user->role instanceof UserRole ? $user->role->value : (string) $user->role;

        $user->name = $validated['name'];
        $user->email = $validated['email'];
        $user->phone = $validated['phone'];
        $user->role = UserRole::tryFrom($newRole->name) ?? $newRole->name;
        $user->role_id = $newRole->id;

        if (!empty($validated['password'])) {
            $user->password = Hash::make($validated['password']);
        }

        $user->save();
        $user->assignedRoles()->sync([$newRole->id]);

        AuditLog::log('staff_updated', $user, [
            'role' => $oldRole,
        ], [
            'role' => $newRole->name,
            'updated_by' => $actor->name,
        ]);

        return redirect()->route('admin.staff.index')->with(
            'success',
            "Staff member '{$user->name}' updated successfully."
        );
    }

    /**
     * Suspend or reactivate a staff member's account.
     */
    public function toggleStatus(Request $request, User $user): RedirectResponse
    {
        $actor = $request->user();

        // OWNER & TECHNICAL AUTHORITY PROTECTION: Protected accounts cannot be suspended
        if ($user->isBusinessAuthority()) {
            abort(403, 'Security Violation: The Business Owner account cannot be suspended or deactivated.');
        }

        if ($user->isTechnicalAuthority()) {
            abort(403, 'Security Violation: The Super Admin account cannot be suspended or deactivated by the Business Owner or any administrator.');
        }

        if (!$actor->isOwner() && !$actor->isTechnicalAuthority() && !$actor->hasPermissionTo('users.suspend')) {
            abort(403, 'Unauthorized to alter staff account status.');
        }

        $priorStatus = $user->status instanceof UserStatus ? $user->status->value : (string) $user->status;
        $newStatus = ($user->status === UserStatus::ACTIVE) ? UserStatus::SUSPENDED : UserStatus::ACTIVE;

        $user->update(['status' => $newStatus]);

        // If suspended, invalidate any active sessions immediately
        if ($newStatus === UserStatus::SUSPENDED) {
            DB::table('sessions')->where('user_id', $user->id)->delete();
        }

        AuditLog::log('staff_status_toggled', $user, [
            'status' => $priorStatus,
        ], [
            'status' => $newStatus->value,
            'toggled_by' => $actor->name,
        ]);

        $actionText = ($newStatus === UserStatus::ACTIVE) ? 'reactivated' : 'suspended';
        return redirect()->route('admin.staff.index')->with(
            'success',
            "Staff account '{$user->name}' has been {$actionText}."
        );
    }

    /**
     * Terminate all active browser sessions for a staff member.
     */
    public function killSessions(Request $request, User $user): RedirectResponse
    {
        $actor = $request->user();

        // OWNER PROTECTION: Super Admin cannot terminate Owner sessions
        if ($user->isBusinessAuthority() && $actor->id !== $user->id) {
            abort(403, 'Security Violation: Super Admin cannot terminate the Owner\'s active sessions.');
        }

        // SUPER ADMIN PROTECTION: Owner cannot terminate Super Admin sessions
        if ($user->isTechnicalAuthority() && $actor->id !== $user->id) {
            abort(403, 'Security Violation: The Business Owner cannot terminate Super Admin active sessions.');
        }

        if (!$actor->isOwner() && !$actor->isTechnicalAuthority() && !$actor->hasPermissionTo('users.sessions.kill')) {
            abort(403, 'Unauthorized to terminate user sessions.');
        }

        $deletedCount = DB::table('sessions')->where('user_id', $user->id)->delete();

        AuditLog::log('staff_sessions_killed', $user, null, [
            'sessions_cleared' => $deletedCount,
            'actor' => $actor->name,
        ]);

        return redirect()->route('admin.staff.index')->with(
            'success',
            "All active sessions ({$deletedCount}) for '{$user->name}' have been terminated. The user will be required to re-login."
        );
    }

    /**
     * Reset Two-Factor Authentication credentials for locked-out staff.
     */
    public function resetMfa(Request $request, User $user): RedirectResponse
    {
        $actor = $request->user();

        // OWNER PROTECTION: Super Admin cannot reset Owner's MFA
        if ($user->isBusinessAuthority() && $actor->id !== $user->id) {
            abort(403, 'Security Violation: Super Admin cannot reset the Owner\'s MFA secret.');
        }

        // SUPER ADMIN PROTECTION: Owner cannot reset Super Admin MFA
        if ($user->isTechnicalAuthority() && $actor->id !== $user->id) {
            abort(403, 'Security Violation: The Business Owner cannot reset Super Admin Two-Factor Authentication.');
        }

        if (!$actor->isOwner() && !$actor->isTechnicalAuthority() && !$actor->hasPermissionTo('users.mfa.reset')) {
            abort(403, 'Unauthorized to reset Two-Factor Authentication.');
        }

        $user->update([
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
        ]);

        // Invalidate active sessions to force fresh login & MFA setup
        DB::table('sessions')->where('user_id', $user->id)->delete();

        AuditLog::log('staff_mfa_reset', $user, null, [
            'target_user' => $user->email,
            'reset_by' => $actor->name,
        ]);

        return redirect()->route('admin.staff.index')->with(
            'success',
            "Two-Factor Authentication for '{$user->name}' has been reset. The user must re-configure 2FA on their next login."
        );
    }
}

