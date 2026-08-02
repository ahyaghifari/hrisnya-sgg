<?php

namespace App\Providers;

use App\Auth\KaryawanAwareUserProvider;
use App\Filament\Candidate\Pages\Auth\LoginResponse as AuthLoginResponse;
use Filament\Auth\Http\Responses\Contracts\LoginResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(LoginResponse::class, AuthLoginResponse::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Auth::provider('eloquent_karyawan_aware', fn ($app, array $config) => new KaryawanAwareUserProvider($app['hash'], $config['model']));
    }
}
