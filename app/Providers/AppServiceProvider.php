<?php

namespace App\Providers;

use App\Auth\KaryawanAwareUserProvider;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Encryption\Encrypter;
use Illuminate\Encryption\MissingAppKeyException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use NotificationChannels\WebPush\WebPushChannel;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->registerPayrollEncrypter();

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

        RateLimiter::for('payroll-slip-email', fn () => Limit::perMinute(35));

        // Laravel tidak scan subfolder migrations; migrasi DB `persuratan` dipisah ke subfolder.
        $this->loadMigrationsFrom(database_path('migrations/persuratan'));

        // Daftarkan provider Socialite 'keycloak' (socialiteproviders/keycloak pakai pola
        // event-based, bukan config/services saja). Laravel 12 tidak punya EventServiceProvider
        // default - didaftarkan di sini.
        Event::listen(function (\SocialiteProviders\Manager\SocialiteWasCalled $event) {
            $event->extendSocialite('keycloak', \SocialiteProviders\Keycloak\Provider::class);
        });
    }

    /**
     * Encrypter khusus nominal payroll, key terpisah dari APP_KEY (config/payroll.php) - biar
     * kompromi APP_KEY (dipakai session/cookie/cache juga) tidak otomatis bongkar nominal
     * payroll. Pola sama seperti EncryptionServiceProvider bawaan Laravel (parseKey +
     * previousKeys), cuma pakai config('payroll.*') bukan config('app.*').
     */
    private function registerPayrollEncrypter(): void
    {
        $this->app->singleton('payroll.encrypter', function ($app) {
            $config = $app->make('config')->get('payroll');

            return (new Encrypter($this->parsePayrollKey($config['key']), $config['cipher']))
                ->previousKeys(array_map(
                    fn ($key) => $this->parsePayrollKey($key),
                    $config['previous_keys'] ?? []
                ));
        });
    }

    private function parsePayrollKey(?string $key): string
    {
        if (empty($key)) {
            throw new MissingAppKeyException('PAYROLL_ENCRYPTION_KEY belum di-set di .env.');
        }

        if (Str::startsWith($key, $prefix = 'base64:')) {
            $key = base64_decode(Str::after($key, $prefix));
        }

        return $key;
    }
}
