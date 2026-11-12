<?php

namespace App\Providers;

use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Vite::prefetch(concurrency: 3);

        // Grant Owner wildcard permission bypass EXCEPT when targeting protected Technical Authority
        Gate::before(function ($user, $ability, $arguments = []) {
            if ($user->isOwner()) {
                $target = is_array($arguments) && isset($arguments[0]) ? $arguments[0] : null;
                if ($target instanceof \App\Models\User && $target->isTechnicalAuthority() && $target->id !== $user->id) {
                    return null; // Defer to UserPolicy to strictly enforce Technical Authority protection
                }
                return true;
            }
        });

        // Dynamic permission lookup
        Gate::after(function ($user, $ability, $result) {
            if ($result !== null) {
                return $result;
            }
            return $user->hasPermissionTo($ability);
        });
    }
}
