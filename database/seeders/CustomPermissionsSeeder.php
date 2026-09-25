<?php

namespace Database\Seeders;

use App\Enum\CustomPermissionEnum;
use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;


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
        // $this->bootstrapSelfServicePermissions();
    }

    /**
     * Permission format Shield "Verb:Subject" buat model tanpa Filament Resource sendiri
     * (Policy-nya ditulis manual di app/Policies/**, tidak pernah kena `shield:generate`) —
     * baris permission-nya dijamin ada di sini sebagai gantinya. UserKoperasi/BpjsDependent/
     * ExtraShiftAssignment/ExecutiveAllowance/Schedule SUDAH DIKELUARKAN dari daftar ini
     * (2026-08-11) karena masing-masing sekarang punya Filament Resource sendiri -
     * permissionnya biar digenerate `shield:generate` seperti Incentive, bukan manual lagi
     * ($subjects sekarang kosong, method ini no-op sampai ada subject manual baru lagi).
     * Tambahkan subject baru ke $subjects HANYA kalau modelnya tidak/tidak akan punya
     * Filament Resource.
     */
    private function seedManualPolicyPermissions(): void
    {
        $subjects = [
            'MeritPointRule',
            'MeritPointTransaction',
            'EmployeeSp',
            'SpRecommendation',
        ];
        $verbs = ['ViewAny', 'View', 'Create', 'Update', 'Delete', 'Restore', 'ForceDelete'];

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
     * PELATIHAN_SAYA, PENGAJUAN_ABSENSI_SAYA, PEMBINAAN_SAYA menggerbangi nav baris 1
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
            CustomPermissionEnum::PEMBINAAN_SAYA->value,
        ];

        $permissions = Permission::whereIn('name', $names)->where('guard_name', 'web')->get();

        Role::where('guard_name', 'web')->get()->each(
            fn (Role $role) => $role->givePermissionTo($permissions)
        );
    }
}
