<?php

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Support\Facades\Route;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function () {
            Route::middleware('web')->group(__DIR__.'/../routes/keycloak.php');
        },
    )
    ->withSchedule(function (Schedule $schedule) {
        // Proses access_logs -> attendance_records tiap 10 detik, incremental (lihat
        // AccessLogScheduleReader::readAndGroupIncremental + SyncAccessLog watermark - tidak
        // scan ulang semua baris tiap kali). Langsung lewat scheduler (bukan queue), sesuai
        // keputusan user - tidak perlu proses queue:work tambahan cuma buat ini.
        // Frekuensi sub-menit WAJIB dijalankan lewat `schedule:work` (Supervisor), bukan cron
        // `schedule:run` per menit.
        $schedule->command('attendance:sync-access-logs')
            ->everyTenSeconds()
            ->withoutOverlapping();

        // Terapkan transisi karyawan (Promosi/Demosi/Mutasi/PHK) yang disetujui dengan tanggal
        // efektif di masa depan, begitu tanggalnya tiba. Jalan sekali sehari lewat cron.
        $schedule->command('transitions:apply-due')
            ->dailyAt('00:15')
            ->withoutOverlapping();

        // Terapkan pengangkatan Karyawan Tetap (Sikartap) yang keputusan Direkturnya approved
        // tapi tanggal TMT-nya masih di depan, begitu tanggalnya tiba.
        $schedule->command('sikartap:apply-due-tetap')
            ->dailyAt('00:20')
            ->withoutOverlapping();
    })
    ->withMiddleware(function (Middleware $middleware) {
        // NB: statefulApi() cuma nge-set flag; middleware Sanctum-nya baru masuk ke grup 'api'
        // bawaan saat getMiddlewareGroups() dibangun. Panggilan group('api', [...]) di bawah ini
        // MENGGANTI seluruh isi grup 'api' (bukan menggabung), jadi harus appendToGroup, bukan group().
        $middleware->statefulApi();
        $middleware->appendToGroup('api', [
            \App\Http\Middleware\ForceJsonResponse::class,
        ]);

        $middleware->alias([
            'portal.access' => \App\Http\Middleware\EnsurePortalPageAccess::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
