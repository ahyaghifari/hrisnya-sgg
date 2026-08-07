<?php

use Illuminate\Database\Migrations\Migration;
use App\Models\Role;
use Spatie\Permission\Models\Permission;

/**
 * 4 halaman rekap (Rekap Presensi, Izin, Cuti, Lembur) - laporan agregat, tidak ada Resource/
 * Policy per model (bukan CRUD). Gerbang SISTEM_ABSENSI diganti VIEW_REPORT_DATA_ABSENSI
 * (`view_report_data_absensi`) - permission ini sudah ada di enum dari lama tapi tidak pernah
 * dipakai di mana pun, akhirnya di-wire di sesi ini (instruksi user: "satukan aja", jangan
 * dipecah per kategori presensi vs izin/cuti/lembur).
 *
 * Grant ke role yang sudah punya sistem_absensi - safety net.
 */
return new class extends Migration
{
    public function up(): void
    {
        $permission = Permission::where('name', 'view_report_data_absensi')->first();

        if (! $permission) {
            return;
        }

        Role::whereHas('permissions', fn ($q) => $q->where('name', 'sistem_absensi'))
            ->get()
            ->each(fn (Role $role) => $role->givePermissionTo($permission));

        app('cache')
            ->store(config('permission.cache.store') != 'default' ? config('permission.cache.store') : null)
            ->forget(config('permission.cache.key'));
    }

    public function down(): void
    {
        // Sengaja tidak di-reverse - mencabut akses lewat rollback berisiko mengunci role
        // dari halaman Rekap tanpa sengaja.
    }
};
