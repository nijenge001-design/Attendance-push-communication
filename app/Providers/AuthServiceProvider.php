<?php

namespace App\Providers;

use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * Laravel 13 does not auto-discover App\Policies\Api\V1.
     */
    public function boot(): void
    {
        Gate::guessPolicyNamesUsing(
            fn (string $modelClass): string => 'App\\Policies\\Api\\V1\\'.class_basename($modelClass).'Policy'
        );
    }
}
