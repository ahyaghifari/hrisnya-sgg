<?php

use Illuminate\Database\Migrations\Migration;
use App\Models\Role;
use Spatie\Permission\Models\Permission;

/**
 * Modul Penilaian Karyawan (Daftar/Jadwal/Template) - dulu semua mount()/nav numpang gate
 * flat SISTEM_PENILAIAN_KARYAWAN, padahal 4 Filament Resource (Assessments/AssessmentTemplates/
 * AssessmentTypes/AssessmentUsers) sudah lama punya Shield permission sendiri (dipakai admin
 * panel), cuma tidak pernah diterapkan ke portal. Sekarang portal ikut pakai permission granular
 * yang sama (ViewAny/Create/Update:Assessments, dst) - lihat DaftarPenilaianPage,
 * JadwalPenilaianPage, TemplatePenilaianPage & pasangan Create/Edit-nya.
 *
 * Permission-nya SUDAH ADA di DB (Shield sudah generate lebih dulu untuk admin panel), jadi di
 * sini cuma grant ke role yang sudah punya sistem_penilaian_karyawan - supaya tidak ada role yang
 * kehilangan akses ke 3 halaman ini akibat perubahan gate.
 */
return new class extends Migration
{
    public function up(): void
    {
        $subjects = ['Assessments', 'AssessmentTemplates', 'AssessmentTypes', 'AssessmentUsers'];
        $verbs = ['ViewAny', 'View', 'Create', 'Update', 'Delete', 'Restore'];

        $permissionNames = [];
        foreach ($subjects as $subject) {
            foreach ($verbs as $verb) {
                $permissionNames[] = "{$verb}:{$subject}";
            }
        }

        $permissions = Permission::whereIn('name', $permissionNames)->get();

        Role::whereHas('permissions', fn ($q) => $q->where('name', 'sistem_penilaian_karyawan'))
            ->get()
            ->each(fn (Role $role) => $role->givePermissionTo($permissions));

        app('cache')
            ->store(config('permission.cache.store') != 'default' ? config('permission.cache.store') : null)
            ->forget(config('permission.cache.key'));
    }

    public function down(): void
    {
        // Sengaja tidak di-reverse - mencabut akses lewat rollback berisiko mengunci role
        // dari modul Penilaian Karyawan tanpa sengaja.
    }
};
