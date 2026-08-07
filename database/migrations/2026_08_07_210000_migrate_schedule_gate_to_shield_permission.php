<?php

use Illuminate\Database\Migrations\Migration;
use App\Models\Role;
use Spatie\Permission\Models\Permission;

/**
 * Modul Jadwal (Schedule) sekarang punya ScheduleResource beneran (instruksi user
 * 2026-08-07) - permission ViewAny/View/Create/Update/Delete/Restore:Schedule digenerate
 * Shield asli lewat `shield:generate --resource=ScheduleResource --option=permissions`,
 * gantikan permission manual yang dulu ditulis tangan (SchedulePolicy + seedManualPolicyPermissions).
 * Create/Update/Delete:Schedule NAMA-nya sama persis dengan yang lama (Shield reuse baris yang
 * sudah ada, grant existing tidak hilang) - cuma ViewAny/View:Schedule yang baru (dulu gerbang
 * SchedulesPage/ScheduleDetailPage numpang SISTEM_ABSENSI, sekarang permission sendiri).
 *
 * Grant ViewAny/View:Schedule ke semua role yang sudah punya sistem_absensi - safety net biar
 * tidak ada yang tiba-tiba kehilangan akses menu Jadwal begitu gate ini aktif.
 */
return new class extends Migration
{
    public function up(): void
    {
        $viewAny = Permission::where('name', 'ViewAny:Schedule')->first();
        $view = Permission::where('name', 'View:Schedule')->first();

        if (! $viewAny || ! $view) {
            return;
        }

        Role::whereHas('permissions', fn ($q) => $q->where('name', 'sistem_absensi'))
            ->get()
            ->each(function (Role $role) use ($viewAny, $view) {
                $role->givePermissionTo([$viewAny, $view]);
            });

        app('cache')
            ->store(config('permission.cache.store') != 'default' ? config('permission.cache.store') : null)
            ->forget(config('permission.cache.key'));
    }

    public function down(): void
    {
        // Sengaja tidak di-reverse - mencabut akses yang sudah di-grant lewat rollback
        // berisiko mengunci role dari menu Jadwal tanpa sengaja.
    }
};
