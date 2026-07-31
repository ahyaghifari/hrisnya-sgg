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
            ['name' => 'Izin Menikah (Karyawan)', 'default_days' => 3, 'description' => '3 hari kerja, atau 5 hari kerja kalau lokasi pernikahan >150km dari tempat kerja - geser tanggal selesai manual kalau berlaku, tidak dihitung otomatis oleh sistem.'],
            ['name' => 'Izin Menikah (Anak Sah Karyawan)', 'default_days' => 2, 'description' => '2 hari kerja.'],
            ['name' => 'Izin Istri Melahirkan/Keguguran', 'default_days' => 2, 'description' => '2 hari kerja.'],
            ['name' => 'Izin Keluarga Inti Meninggal', 'default_days' => 3, 'description' => 'Suami/istri, orang tua, atau anak sah karyawan meninggal - 3 hari kerja.'],
            ['name' => 'Izin Keluarga Serumah Meninggal', 'default_days' => 2, 'description' => 'Anggota keluarga yang tinggal serumah (di luar suami/istri/ortu/anak) meninggal - 2 hari kerja.'],
            ['name' => 'Izin Mengkhitankan/Membaptiskan Anak', 'default_days' => 2, 'description' => '2 hari kerja.'],
            ['name' => 'Izin Musibah', 'default_days' => null, 'description' => 'Kebakaran, kebanjiran, atau musibah lain yang menimpa karyawan - lama izin atas pertimbangan pimpinan perusahaan, tidak ada jumlah hari baku. Isi tanggal sesuai kesepakatan dengan atasan/HC.'],
            ['name' => 'Izin Ibadah Haji', 'default_days' => null, 'description' => 'Sesuai durasi ibadah haji ditambah 2 hari sebelum keberangkatan dan 2 hari setelah tiba kembali di tanah air. Isi tanggal mulai/selesai sesuai jadwal keberangkatan-kepulangan yang sebenarnya.'],
        ];

        foreach (Companies::all() as $company) {
            foreach ($khusus as $row) {
                $type = PermissionTypes::firstOrCreate(
                    ['company_id' => $company->id, 'name' => $row['name']],
                    ['category' => PermissionTypeCategoryEnum::IZIN_KHUSUS->value, 'default_days' => $row['default_days'], 'description' => $row['description']]
                );

                // Backfill description ke baris yang sudah ada dari sebelum kolom ini ada
                // (kolom baru 2026-07-31) - cuma isi kalau masih kosong, tidak menimpa kalau
                // admin sudah pernah edit deskripsinya sendiri lewat /admin.
                if ($type->wasRecentlyCreated === false && $type->description === null) {
                    $type->update(['description' => $row['description']]);
                }
            }
        }
    }
}
