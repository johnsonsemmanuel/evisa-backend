<?php

namespace App\Providers;

use App\Enums\UserRole;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class HorizonServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap services.
     * Restrict Horizon dashboard to admin and super_admin roles.
     */
    public function boot(): void
    {
        Gate::define('viewHorizon', function ($user) {
            if (!$user) {
                return false;
            }
            $roleValue = $user->role instanceof UserRole
                ? $user->role->value
                : $user->role;
            return in_array($roleValue, ['admin', 'super_admin']);
        });
    }
}
