<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;

/**
 * Fitur baru "Export Excel" di halaman Karyawan (/kepegawaian/karyawan) - permission manual
 * EXPORT_DATA_KARYAWAN (App\Enum\CustomPermissionEnum), datanya sensitif (NIK/KK/BPJS/rekening
 * bank) jadi sengaja TIDAK auto-grant ke role manapun (beda dari IMPORT_KARYAWAN yang dulu
 * dikasih otomatis ke pemegang Create:User) - harus di-assign manual per role lewat /hak-akses.
 */
return new class extends Migration
{
    public function up(): void
    {
        Permission::firstOrCreate(['name' => 'export_data_karyawan', 'guard_name' => 'web']);

        app('cache')
            ->store(config('permission.cache.store') != 'default' ? config('permission.cache.store') : null)
            ->forget(config('permission.cache.key'));
    }

    public function down(): void
    {
        // Sengaja tidak di-reverse - mencabut permission ini lewat rollback bisa mengunci role
        // dari fitur export tanpa sengaja.
    }
};
