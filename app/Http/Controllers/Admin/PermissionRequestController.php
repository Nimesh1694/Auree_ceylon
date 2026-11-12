<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Permission;
use App\Models\Role;
use App\Models\RolePermissionRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PermissionRequestController extends Controller
{
    /**
     * Display a listing of permission change requests.
     */
    public function index(Request $request): Response
    {
        $status = $request->input('status', 'all');

        $query = RolePermissionRequest::with(['role', 'permission', 'requester', 'reviewer'])
            ->latest('id');

        if ($status !== 'all') {
            $query->where('status', $status);
        }

        $requests = $query->paginate(15)->withQueryString();

        return Inertia::render('Admin/Roles/PermissionRequests', [
            'requests' => $requests,
            'currentStatus' => $status,
            'isOwner' => $request->user()->isOwner(),
        ]);
    }

    /**
     * Store a newly submitted permission change request.
     */
    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();

        if (!$user->isOwner() && !$user->hasPermissionTo('roles.update')) {
            abort(403, 'Unauthorized to request role permissions.');
        }

        $validated = $request->validate([
            'role_id' => ['required', 'exists:roles,id'],
            'permission_id' => ['required', 'exists:permissions,id'],
            'reason' => ['required', 'string', 'min:5', 'max:1000'],
        ]);

        $role = Role::findOrFail($validated['role_id']);
        if ($role->is_protected) {
            abort(403, 'Cannot request permissions for a protected role.');
        }

        $permissionRequest = RolePermissionRequest::create([
            'role_id' => $role->id,
            'permission_id' => $validated['permission_id'],
            'requested_by' => $user->id,
            'status' => 'pending',
            'reason' => $validated['reason'],
        ]);

        AuditLog::log('role_permission_requested', $permissionRequest, null, [
            'role' => $role->name,
            'permission_id' => $validated['permission_id'],
            'reason' => $validated['reason'],
        ]);

        return redirect()->back()->with(
            'success',
            'Permission change request submitted successfully to the Business Owner for review.'
        );
    }

    /**
     * Approve a pending permission change request (Owner Authority Only).
     */
    public function approve(Request $request, RolePermissionRequest $permissionRequest): RedirectResponse
    {
        $user = $request->user();

        // STRICT SECURITY RULE: Only the Owner may approve financial permissions
        if (!$user->isOwner()) {
            abort(403, 'Security Violation: Only the Business Owner possesses the authority to approve financial permissions.');
        }

        // STRICT SECURITY RULE: Self-approval is forbidden
        if ($permissionRequest->requested_by === $user->id) {
            abort(403, 'Security Violation: Administrators are strictly forbidden from approving their own permission requests.');
        }

        if (!$permissionRequest->isPending()) {
            return redirect()->back()->with('error', 'This permission request has already been processed.');
        }

        $validated = $request->validate([
            'review_notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $permissionRequest->approve($user, $validated['review_notes'] ?? null);

        AuditLog::log('role_permission_request_approved', $permissionRequest, [
            'status' => 'pending',
        ], [
            'status' => 'approved',
            'role' => $permissionRequest->role->name,
            'permission' => $permissionRequest->permission->name,
            'reviewer' => $user->name,
            'notes' => $validated['review_notes'] ?? null,
        ]);

        return redirect()->back()->with(
            'success',
            "Permission '{$permissionRequest->permission->display_name}' has been authorized and attached to role '{$permissionRequest->role->display_name}'."
        );
    }

    /**
     * Reject a pending permission change request (Owner Authority Only).
     */
    public function reject(Request $request, RolePermissionRequest $permissionRequest): RedirectResponse
    {
        $user = $request->user();

        // STRICT SECURITY RULE: Only the Owner may reject requests
        if (!$user->isOwner()) {
            abort(403, 'Security Violation: Only the Business Owner possesses the authority to review permission requests.');
        }

        if (!$permissionRequest->isPending()) {
            return redirect()->back()->with('error', 'This permission request has already been processed.');
        }

        $validated = $request->validate([
            'review_notes' => ['required', 'string', 'min:3', 'max:1000'],
        ]);

        $permissionRequest->reject($user, $validated['review_notes']);

        AuditLog::log('role_permission_request_rejected', $permissionRequest, [
            'status' => 'pending',
        ], [
            'status' => 'rejected',
            'role' => $permissionRequest->role->name,
            'permission' => $permissionRequest->permission->name,
            'reviewer' => $user->name,
            'notes' => $validated['review_notes'],
        ]);

        return redirect()->back()->with(
            'success',
            "Permission request for '{$permissionRequest->role->display_name}' was rejected."
        );
    }
}

