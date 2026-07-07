<?php

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withSchedule(function (Schedule $schedule) {
        // Reset monthly leave balances on the 1st of every month at midnight
        $schedule->command('leave:reset-balance monthly')
            ->monthlyOn(1, '00:00');

        // Reset yearly leave balances on January 1st every year at midnight
        $schedule->command('leave:reset-balance yearly')
            ->yearlyOn(1, 1, '00:00');
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
