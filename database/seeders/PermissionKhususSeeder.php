<?php

namespace Database\Seeders;

use App\Enum\Attendance\PermissionTypeCategoryEnum;
use App\Models\Master\Attendance\PermissionTypes;
use App\Models\Master\Companies;
use Illuminate\Database\Seeder;

/**
 * Master data 8 baris Izin Khusus per company, lihat knowledge/markdown/rencana-izin-empat-kategori.md
 * §2 & §3.4. Izin Sakit SENGAJA tidak lagi di sini (dulu sempat 1 baris juga, sudah dihapus -
 * Sakit itu flat/tidak ada sub-jenis, category-nya di-set langsung dari kelompok di form
 * pengajuan tanpa perlu master sama sekali, lihat diskusi lanjutan 2026-07-31).
 *
 * Sengaja TIDAK dipanggil dari DatabaseSeeder — dijalankan manual, idempotent lewat
 * firstOrCreate (company_id + name):
 *   php artisan db:seed --class=PermissionKhususSeeder
 */
class PermissionKhususSeeder extends Seeder
{
    public function run(): void
    {
        $khusus = [
            ['name' => 'Izin Menikah (Karyawan)', 'default_days' => 3],
            ['name' => 'Izin Menikah (Anak Sah Karyawan)', 'default_days' => 2],
            ['name' => 'Izin Istri Melahirkan/Keguguran', 'default_days' => 2],
            ['name' => 'Izin Keluarga Inti Meninggal', 'default_days' => 3],
            ['name' => 'Izin Keluarga Serumah Meninggal', 'default_days' => 2],
            ['name' => 'Izin Mengkhitankan/Membaptiskan Anak', 'default_days' => 2],
            ['name' => 'Izin Musibah', 'default_days' => null],
            ['name' => 'Izin Ibadah Haji', 'default_days' => null],
        ];

        foreach (Companies::all() as $company) {
            foreach ($khusus as $row) {
                PermissionTypes::firstOrCreate(
                    ['company_id' => $company->id, 'name' => $row['name']],
                    ['category' => PermissionTypeCategoryEnum::IZIN_KHUSUS->value, 'default_days' => $row['default_days']]
                );
            }
        }
    }
}
