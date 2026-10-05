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
        // Proses access_logs -> attendance_records tiap 20 detik, incremental (lihat
        // AccessLogScheduleReader::readAndGroupIncremental + SyncAccessLog watermark - tidak
        // scan ulang semua baris tiap kali). Langsung lewat scheduler (bukan queue), sesuai
        // keputusan user - tidak perlu proses queue:work tambahan cuma buat ini.
        // Frekuensi sub-menit WAJIB dijalankan lewat `schedule:work` (Supervisor), bukan cron
        // `schedule:run` per menit.
        // withoutOverlapping(5): kunci kedaluwarsa 5 menit, BUKAN bawaan Laravel 1440 menit (24 jam).
        // Kalau prosesnya mati paksa (server restart/deploy/OOM), kunci bawaan tidak pernah dilepas
        // dan sinkron presensi berhenti total SEHARIAN tanpa error apa pun - gejalanya persis
        // "sebagian orang tidak masuk attendance_records". Dengan 5 menit, paling lama tertunda
        // 5 menit lalu jalan lagi sendiri; run normal jauh di bawah itu (100 karyawan ~3 detik).
        $schedule->command('attendance:sync-access-logs')
            ->everyTwentySeconds()
            ->withoutOverlapping(5);

        // Jaring pengaman buat sync di atas: baris yang masuk ke access_logs belakangan tapi
        // jamnya lebih tua dari watermark tidak akan pernah terbaca lagi oleh jalur incremental
        // (jam device antar lokasi tidak sinkron, device offline yang mengirim susulan). Sapuan
        // ini tidak memakai watermark sama sekali - baca ulang sehari penuh lewat readAndGroup(),
        // tapi HANYA buat orang yang scan-nya tidak nyambung dengan attendance_records, jadi di
        // hari normal cuma 2 query agregat lalu berhenti. Lihat SweepMissedAccessLogs.
        $schedule->command('attendance:sweep-missed-scans')
            ->everyTenMinutes()
            ->withoutOverlapping(10);

        // Shift lintas tengah malam: scan pulangnya jatuh di tanggal berikutnya, jadi tanggal
        // kemarin masih perlu disapu sampai dini hari lewat.
        // "yesterday" sengaja dikirim sebagai kata, bukan tanggal jadi - closure ini cuma dievaluasi
        // sekali saat boot, dan `schedule:work` hidup berhari-hari, jadi tanggal hasil now() di sini
        // akan membeku di tanggal server terakhir kali restart.
        $schedule->command('attendance:sweep-missed-scans --date=yesterday')
            ->everyTenMinutes()
            ->between('00:00', '03:00')
            ->withoutOverlapping(10);

        // Terapkan transisi karyawan (Promosi/Demosi/Mutasi/PHK) yang disetujui dengan tanggal
        // efektif di masa depan, begitu tanggalnya tiba. Jalan sekali sehari lewat cron.
        // Tugas Board berulang yang periodenya lewat aktif lagi untuk periode berikutnya (awal hari).
        $schedule->command('tasks:resume-recurring')
            ->dailyAt('00:05')
            ->withoutOverlapping();

        $schedule->command('transitions:apply-due')
            ->dailyAt('00:15')
            ->withoutOverlapping();

        // Terapkan pengangkatan Karyawan Tetap (Sikartap) yang keputusan Direkturnya approved
        // tapi tanggal TMT-nya masih di depan, begitu tanggalnya tiba.
        $schedule->command('sikartap:apply-due-tetap')
            ->dailyAt('00:20')
            ->withoutOverlapping();

        // Cache tampilan widget Karir (user_hr_balances.active_sp_level) bisa basi murni karena
        // employee_sps.end_date lewat tanpa event apa pun - lihat komentar migration
        // user_hr_balances & planning/2026-09-23-sp-dan-merit-point.md §3.5.
        $schedule->command('sp:refresh-balances')
            ->dailyAt('00:25')
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
