<?php

namespace Database\Seeders;

use App\Models\Master\Companies;
use Illuminate\Database\Seeder;
use App\Models\Role;

class RoleSeeder extends Seeder
{
    /**
     * "Super Admin" cuma buat company holding — company lain cuma sampai "Admin".
     * Perbaikan data yang sudah terlanjur ada ditangani di migration
     * 2026_07_08_230229_sync_filament_shield_permission_catalog.php.
     */
    private const HOLDING_COMPANY_ID = '01J2F0C8KQ9M7D4S8RBX6E1ZVP';

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $roles = [
            'Direktur',
            'HC Supervisor',
            'HC Manager',
            'Kasubag / Staff Kepegawaian',
            'Staff Finance',
            'Learning & Development',
            'Manager / Kepala Bidang',
            'Supervisor / Kepala Ruangan',
            'Karyawan / Staff',
        ];

        Companies::query()
            ->select('id')
            ->chunk(50, function ($companies) use ($roles) {
                foreach ($companies as $company) {
                    $topRole = $company->id === self::HOLDING_COMPANY_ID ? 'Super Admin' : 'Admin';

                    foreach ([$topRole, ...$roles] as $roleName) {
                        Role::firstOrCreate([
                            'name'       => $roleName,
                            'guard_name' => 'web',
                            'company_id' => $company->id,
                        ]);
                    }
                }
            });
    }
}
