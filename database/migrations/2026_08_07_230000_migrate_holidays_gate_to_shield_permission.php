<?php

use Illuminate\Database\Migrations\Migration;
use App\Models\Role;
use Spatie\Permission\Models\Permission;

/**
 * HolidayResource+HolidaysPolicy+permission Shield sudah ada dari awal (Create/Update/Delete
 * di HolidaysPage.php malah sudah pakai Policy dari awal) - cuma mount() (ViewAny) yang masih
 * numpang SISTEM_ABSENSI, diganti ViewAny:Holidays di sesi ini.
 *
 * Grant ViewAny/View:Holidays ke role yang sudah punya sistem_absensi - safety net.
 */
return new class extends Migration
{
    public function up(): void
    {
        $viewAny = Permission::where('name', 'ViewAny:Holidays')->first();
        $view = Permission::where('name', 'View:Holidays')->first();

        if (! $viewAny || ! $view) {
            return;
        }

        Role::whereHas('permissions', fn ($q) => $q->where('name', 'sistem_absensi'))
            ->get()
            ->each(fn (Role $role) => $role->givePermissionTo([$viewAny, $view]));

        app('cache')
            ->store(config('permission.cache.store') != 'default' ? config('permission.cache.store') : null)
            ->forget(config('permission.cache.key'));
    }

    public function down(): void
    {
        // Sengaja tidak di-reverse - mencabut akses lewat rollback berisiko mengunci role
        // dari menu Hari Libur tanpa sengaja.
    }
};
