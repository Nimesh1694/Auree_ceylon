<?php

namespace App\Http\Middleware;

use App\Enums\UserRole;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserHasRole
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     * @param  string  ...$roles
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if (!$user) {
            return redirect()->route('login');
        }

        if (!$user->isActive()) {
            auth()->logout();
            return redirect()->route('login')->withErrors([
                'email' => 'Your account is suspended or awaiting approval.',
            ]);
        }

        $userRoleValue = $user->role instanceof UserRole ? $user->role->value : (string) $user->role;

        // Special aliases
        if (in_array('management', $roles, true) && $user->isManagement()) {
            return $next($request);
        }

        if (in_array('agent', $roles, true) && $user->isAgent()) {
            return $next($request);
        }

        if (in_array($userRoleValue, $roles, true)) {
            return $next($request);
        }

        // Backward compatibility: If route asks for 'it_manager' and user is 'super_admin' (or vice-versa)
        if (in_array('it_manager', $roles, true) && ($userRoleValue === 'super_admin' || $userRoleValue === 'it_manager')) {
            return $next($request);
        }
        if (in_array('super_admin', $roles, true) && ($userRoleValue === 'super_admin' || $userRoleValue === 'it_manager')) {
            return $next($request);
        }

        abort(403, 'Unauthorized access.');
    }
}
