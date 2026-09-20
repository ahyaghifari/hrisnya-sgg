<?php

namespace Database\Seeders;

use App\Models\Master\Attendance\LeaveTypes;
use App\Models\Master\Attendance\PermissionTypes;
use Illuminate\Database\Seeder;

class AttendanceRequestTypeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // "Cuti Sakit" sengaja tidak ada — dikonfirmasi user tidak masuk sistem (lihat
        // planning/2026-07-19-revisi-absensi-cutoff-kuota-izin-widget-pimpinan.md §7).
        $leaveTypes = [
            'Cuti Tahunan',
            'Cuti Melahirkan',
            'Cuti Besar',
            'Cuti Karena Alasan Penting',
            'Cuti Menikah',
        ];

        $permissionTypes = [
            'Izin Terlambat',
            'Izin Pulang Lebih Awal',
            'Izin Tidak Masuk',
            'Izin Keluar Kantor',
            'Izin Medis',
            'Izin Urusan Keluarga',
        ];

        foreach ($leaveTypes as $name) {
            LeaveTypes::firstOrCreate(['name' => $name]);
        }

        foreach ($permissionTypes as $name) {
            PermissionTypes::firstOrCreate(['name' => $name]);
        }

    }
}
