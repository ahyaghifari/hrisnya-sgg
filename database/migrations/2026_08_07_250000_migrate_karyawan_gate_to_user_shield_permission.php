<?php

use Illuminate\Database\Migrations\Migration;
use App\Models\Role;
use Spatie\Permission\Models\Permission;

/**
 * Halaman Karyawan portal (KaryawanPage/CreateKaryawan/EditKaryawan) - EmployeeResource
 * (admin, model User) sudah lama punya permission Shield lengkap (ViewAny/View/Create/Update/
 * Delete/Restore:User), tapi portalnya numpang SISTEM_KEPEGAWAIAN + Policy dobel (redundan).
 * Diganti murni ke permission Shield User di sesi ini. Sekalian ketauan bug: nav dropdown
 * ($canViewKaryawan) selama ini cek 'ViewAny:Karyawan' - permission yang TIDAK PERNAH ADA di
 * DB (typo/subject salah, seharusnya 'ViewAny:User') - link "Karyawan" jadi tidak pernah
 * tampil buat siapa pun walau mount()-nya lolos lewat SISTEM_KEPEGAWAIAN. Sudah dibenerin.
 * KaryawanPage::restore() juga sebelumnya tidak ada cek permission restore sama sekali
 * (cuma cek scope company) - ditambahkan can('restore', User::class).
 *
 * Grant ViewAny/View/Create/Update/Restore:User ke role yang sudah punya sistem_kepegawaian -
 * safety net.
 */
return new class extends Migration
{
    public function up(): void
    {
        $names = ['ViewAny:User', 'View:User', 'Create:User', 'Update:User', 'Restore:User'];
        $permissions = Permission::whereIn('name', $names)->get();

        if ($permissions->count() !== count($names)) {
            return;
        }

        Role::whereHas('permissions', fn ($q) => $q->where('name', 'sistem_kepegawaian'))
            ->get()
            ->each(fn (Role $role) => $role->givePermissionTo($permissions));

        app('cache')
            ->store(config('permission.cache.store') != 'default' ? config('permission.cache.store') : null)
            ->forget(config('permission.cache.key'));
    }

    public function down(): void
    {
        // Sengaja tidak di-reverse - mencabut akses lewat rollback berisiko mengunci role
        // dari menu Karyawan tanpa sengaja.
    }
};
