<?php

namespace Database\Seeders;

use App\Enum\CustomPermissionEnum;
use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

/**
 * Permission "manual" (bukan format Shield Verb:Subject, tidak disentuh shield:generate) —
 * dipakai buat gerbang akses global ke fitur tertentu (mis. seluruh menu Keuangan di portal
 * karyawan), bukan permission CRUD per-Resource biasa. Assignment ke role dilakukan manual
 * lewat halaman Roles (/admin/roles), seeder ini cuma mastiin baris permission-nya ada.
 *
 * Sengaja TIDAK dipanggil dari DatabaseSeeder — dijalankan manual pas dibutuhkan (termasuk di
 * production, aman idempotent lewat firstOrCreate). Kalau ada permission manual baru ke depannya,
 * tambahkan case-nya di App\Enum\CustomPermissionEnum, lalu jalankan ulang:
 *   php artisan db:seed --class=CustomPermissionsSeeder
 */
class CustomPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        foreach (CustomPermissionEnum::cases() as $permission) {
            Permission::firstOrCreate([
                'name' => $permission->value,
                'guard_name' => 'web',
            ]);
        }

        $this->seedManualPolicyPermissions();
        $this->bootstrapSelfServicePermissions();
    }

    /**
     * Permission format Shield "Verb:Subject" tapi untuk model yang TIDAK punya Filament
     * Resource (jadi `shield:generate` tidak pernah menyentuhnya) — Policy-nya ditulis manual
     * di app/Policies/**, baris permission-nya dijamin ada di sini. Tambahkan subject baru ke
     * $subjects kalau ada Policy manual baru dengan pola sama.
     */
    private function seedManualPolicyPermissions(): void
    {
        $subjects = ['UserKoperasi', 'Schedule'];
        $verbs = ['ViewAny', 'View', 'Create', 'Update', 'Delete'];

        foreach ($subjects as $subject) {
            foreach ($verbs as $verb) {
                Permission::firstOrCreate([
                    'name' => "{$verb}:{$subject}",
                    'guard_name' => 'web',
                ]);
            }
        }
    }

    /**
     * PELATIHAN_SAYA, PENGAJUAN_ABSENSI_SAYA, COACHING_COUNSELING_SAYA menggerbangi nav baris 1
     * (Pelatihan, Pengajuan Absensi, Counseling, Coaching Saya) yang SEBELUMNYA terbuka buat
     * siapa saja yang login (tanpa permission). Supaya tidak ada role yang tiba-tiba kehilangan
     * akses begitu gerbang ini aktif, ketiga permission ini di-assign otomatis ke SEMUA role yang
     * sudah ada saat seeder ini jalan — bukan pola umum permission manual lain (yang sengaja
     * dibiarkan kosong, di-assign manual lewat /admin/roles). Role baru ke depannya tetap harus
     * di-assign manual seperti biasa.
     */
    private function bootstrapSelfServicePermissions(): void
    {
        $names = [
            CustomPermissionEnum::PELATIHAN_SAYA->value,
            CustomPermissionEnum::PENGAJUAN_ABSENSI_SAYA->value,
            CustomPermissionEnum::COACHING_COUNSELING_SAYA->value,
        ];

        $permissions = Permission::whereIn('name', $names)->where('guard_name', 'web')->get();

        Role::where('guard_name', 'web')->get()->each(
            fn (Role $role) => $role->givePermissionTo($permissions)
        );
    }
}
