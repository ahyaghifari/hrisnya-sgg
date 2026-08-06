<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;

/**
 * `app/Filament/Pages/OrganizationStructure.php` (admin panel) dihapus total - duplikat,
 * portal `/struktur-organisasi` (`App\Livewire\OrganizationStructure`) sudah lebih lengkap
 * (company selector + link edit jabatan) dan sengaja bebas akses buat siapa saja yang
 * login. `View:OrganizationStructure` sekarang beneran orphan (bukan seperti dugaan awal
 * di 2026_08_07_040000 yang salah - itu sudah di-rollback+hapus - Page-nya WAKTU ITU masih
 * ada, sekarang sudah tidak ada lagi).
 */
return new class extends Migration
{
    public function up(): void
    {
        $permission = Permission::where('name', 'View:OrganizationStructure')->first();

        if (! $permission) {
            return;
        }

        DB::table('model_has_permissions')->where('permission_id', $permission->id)->delete();
        DB::table('role_has_permissions')->where('permission_id', $permission->id)->delete();

        $permission->delete();

        app('cache')
            ->store(config('permission.cache.store') != 'default' ? config('permission.cache.store') : null)
            ->forget(config('permission.cache.key'));
    }

    public function down(): void
    {
        // Sengaja tidak di-reverse - pola sama migration cleanup sebelumnya.
    }
};
