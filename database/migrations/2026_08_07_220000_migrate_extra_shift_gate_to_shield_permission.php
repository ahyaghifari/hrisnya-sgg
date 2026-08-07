<?php

use Illuminate\Database\Migrations\Migration;
use App\Models\Role;
use Spatie\Permission\Models\Permission;

/**
 * ExtraShiftAssignmentResource+Policy+permission Shield SUDAH ADA dari sebelumnya (tidak perlu
 * dibuat baru, beda dari Schedule kemarin) - tapi portal ExtraShiftPage belum benar-benar
 * memakainya, masih numpang SISTEM_ABSENSI (lihat) + KELOLA_JADWAL_SHIFT (kelola: tambah &
 * hapus, dijadikan 1 gerbang, padahal permission Shield-nya sudah granular Create/Delete
 * terpisah). Diganti ke ViewAny:ExtraShiftAssignment (lihat) dan Create/Delete:ExtraShiftAssignment
 * (kelola) di sesi ini.
 *
 * Grant ViewAny/View ke role yang sudah punya sistem_absensi, dan Create/Delete ke role yang
 * sudah punya kelola_jadwal_shift - safety net biar tidak ada yang tiba-tiba kehilangan akses.
 */
return new class extends Migration
{
    public function up(): void
    {
        $viewAny = Permission::where('name', 'ViewAny:ExtraShiftAssignment')->first();
        $view = Permission::where('name', 'View:ExtraShiftAssignment')->first();
        $create = Permission::where('name', 'Create:ExtraShiftAssignment')->first();
        $delete = Permission::where('name', 'Delete:ExtraShiftAssignment')->first();

        if (! $viewAny || ! $view || ! $create || ! $delete) {
            return;
        }

        Role::whereHas('permissions', fn ($q) => $q->where('name', 'sistem_absensi'))
            ->get()
            ->each(fn (Role $role) => $role->givePermissionTo([$viewAny, $view]));

        Role::whereHas('permissions', fn ($q) => $q->where('name', 'kelola_jadwal_shift'))
            ->get()
            ->each(fn (Role $role) => $role->givePermissionTo([$create, $delete]));

        app('cache')
            ->store(config('permission.cache.store') != 'default' ? config('permission.cache.store') : null)
            ->forget(config('permission.cache.key'));
    }

    public function down(): void
    {
        // Sengaja tidak di-reverse - mencabut akses lewat rollback berisiko mengunci role
        // dari fitur Extra Shift tanpa sengaja.
    }
};
