<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Artisan;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * Sinkronkan katalog permission lewat Filament Shield (bezhansalleh/filament-shield) —
 * gantikan cara lama (PermissionsSeeder dirawat manual) dengan generate otomatis dari
 * Resource/Page/Widget yang terdaftar di panel /admin, pakai penamaan default Shield
 * sendiri (PascalCase, berbasis nama Model — `Update:Employee`, bukan `edit_karyawan`).
 *
 * SENGAJA TIDAK mempertahankan nama permission lama (`view_karyawan` dkk, ~197 baris
 * dari PermissionsSeeder) — permission lama itu jadi orphan (tidak dihapus, tapi tidak
 * lagi dipakai Gate check manapun setelah canAccess() Resource diganti ke Policy Shield).
 * role_has_permissions yang sudah live di 8 company perlu di-assign ULANG manual ke
 * permission baru lewat RoleForm setelah migration ini jalan — lihat
 * planning/2026-07-08-migrasi-filament-shield.md §9.
 *
 * Digabung dengan 2 seeder lama yang niatnya sekarang jadi bagian dari migration ini
 * (bukan dijalankan terpisah lagi):
 * - RenameCompanySuperAdminToAdminSeeder: role "Super Admin" seharusnya cuma ada di
 *   company holding — safety net (no-op kalau datanya sudah benar), penting karena
 *   config filament-shield.php `super_admin.name = 'Super Admin'` bikin SIAPA PUN yang
 *   pegang role bernama itu (company manapun) otomatis bypass semua Gate check.
 * - GrantPermissionManagementToSuperAdminSeeder: niatnya kasih akses kelola
 *   /admin/permissions ke Super Admin — sudah tergantikan penuh (dan lebih lengkap,
 *   semua permission bukan cuma 4) lewat syncPermissions() di bawah, diterapkan ke
 *   SEMUA baris role "Super Admin" (termasuk yang lama, company holding), bukan cuma
 *   yang baru dibuat (company_id NULL).
 *
 * Idempotent — aman dijalankan ulang (shield:generate pakai firstOrCreate, bukan insert buta;
 * syncPermissions mengganti isi assignment, bukan menambah dobel).
 */
return new class extends Migration
{
    private const HOLDING_COMPANY_ID = '01J2F0C8KQ9M7D4S8RBX6E1ZVP';

    public function up(): void
    {
        Artisan::call('shield:generate', [
            '--panel' => 'admin',
            '--all' => true,
            '--option' => 'permissions',
            '--no-interaction' => true,
        ]);

        // Safety net dari RenameCompanySuperAdminToAdminSeeder — role "Super Admin"
        // semestinya cuma ada di company holding, company lain harusnya "Admin".
        Role::query()
            ->where('name', 'Super Admin')
            ->where('guard_name', 'web')
            ->where('company_id', '!=', self::HOLDING_COMPANY_ID)
            ->update(['name' => 'Admin']);

        // Role "Super Admin" global (company_id NULL) — beda baris dari "Super Admin"
        // yang sudah ada (company_id terisi ID company holding), constraint unique-nya
        // ['name', 'guard_name', 'company_id'] jadi tidak bentrok.
        Role::firstOrCreate([
            'name' => 'Super Admin',
            'guard_name' => 'web',
            'company_id' => null,
        ]);

        // Semua permission ke SEMUA baris "Super Admin" (holding + global) —
        // gantikan GrantPermissionManagementToSuperAdminSeeder yang cuma kasih 4 permission
        // spesifik ke role lama; sekarang diperluas jadi semua permission sekalian.
        $allPermissions = Permission::where('guard_name', 'web')->get();

        Role::query()
            ->where('name', 'Super Admin')
            ->where('guard_name', 'web')
            ->chunk(50, function ($roles) use ($allPermissions) {
                foreach ($roles as $role) {
                    $role->syncPermissions($allPermissions);
                }
            });
    }

    public function down(): void
    {
        // Sengaja tidak di-reverse — permission yang sudah live di role_has_permissions
        // (8 company) tidak boleh ikut terhapus lewat rollback migration ini.
    }
};
