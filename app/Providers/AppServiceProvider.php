<?php

namespace App\Providers;

use App\Auth\KaryawanAwareUserProvider;
use App\Filament\Candidate\Pages\Auth\LoginResponse as AuthLoginResponse;
use Filament\Auth\Http\Responses\Contracts\LoginResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\ServiceProvider;
use NotificationChannels\WebPush\WebPushChannel;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(LoginResponse::class, AuthLoginResponse::class);

        // ponytail: DB local (hris_db) berisi push_subscriptions asli 4 karyawan (bukan data
        // dummy) - kalau APP_ENV=local, WebPushChannel di-no-op total supaya testing notifikasi
        // (approval, dst) tidak beneran ngirim push ke HP mereka. Channel 'database' (bell UI)
        // TIDAK disentuh, tetap jalan normal untuk testing. Cabut guard ini kalau nanti ada
        // kebutuhan sungguhan test WebPush di local.
        if ($this->app->environment('local')) {
            $this->app->bind(WebPushChannel::class, fn () => new class {
                public function send($notifiable, $notification): void {}
            });
        }
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Auth::provider('eloquent_karyawan_aware', fn ($app, array $config) => new KaryawanAwareUserProvider($app['hash'], $config['model']));
    }
}
