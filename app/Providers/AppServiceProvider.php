<?php

namespace App\Providers;

use App\Models\User;
use App\Support\MeetingUi;
use Illuminate\Foundation\AliasLoader;
use Illuminate\Support\Facades\Gate;
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
        AliasLoader::getInstance()->alias('MUi', MeetingUi::class);

        Gate::define('admin', fn (User $user) => $user->is_admin && $user->is_active);
        Gate::define('support', fn (User $user) => $user->isSupport());
        Gate::define('reports', fn (User $user) => $user->isReporter());
    }
}
