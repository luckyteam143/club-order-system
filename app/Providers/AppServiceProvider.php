<?php

namespace App\Providers;

use App\Mail\Transport\MicrosoftGraphTransport;
use App\Models\Order;
use App\Models\Setting;
use App\Models\User;
use App\Observers\OrderObserver;
use App\Policies\OrderPolicy;
use App\Support\MailConfigurator;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(
            \Filament\Http\Responses\Auth\Contracts\LoginResponse::class,
            \App\Http\Responses\Auth\LoginResponse::class,
        );
    }

    public function boot(): void
    {
        Gate::policy(Order::class, OrderPolicy::class);

        // master_admin always passes every permission check, so it doesn't
        // need every current (and future) permission explicitly synced to it.
        Gate::before(fn (User $user, string $ability) => $user->hasRole('master_admin') ? true : null);

        Order::observe(OrderObserver::class);

        Mail::extend('graph', fn (array $config) => new MicrosoftGraphTransport(
            $config['tenant_id'] ?? '',
            $config['client_id'] ?? '',
            $config['client_secret'] ?? '',
            $config['mailbox'] ?? '',
        ));

        $this->applyConfiguredTimezone();

        if (Schema::hasTable('settings')) {
            MailConfigurator::apply();
        }
    }

    /**
     * Admin-configurable (Settings > General > Time Zone) rather than fixed
     * in .env, so it can change without a deploy — config('app.timezone')
     * still supplies the fallback (and covers artisan/queue contexts that
     * run before the DB is reachable, e.g. early in `migrate`).
     */
    private function applyConfiguredTimezone(): void
    {
        if (! Schema::hasTable('settings')) {
            return;
        }

        $timezone = Setting::get('app_timezone', config('app.timezone'));

        date_default_timezone_set($timezone);
        config(['app.timezone' => $timezone]);
    }
}
