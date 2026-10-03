<?php

namespace Database\Seeders;

use App\Models\Master\Companies;
use App\Models\Persuratan\Perusahaan;
use Illuminate\Database\Seeder;

/**
 * Perusahaan penerbit surat (DB `persuratan`), data dari sistem lama `audit_syifag`
 * (tabel perusahaan + kode_surat_perusahaan). `id` SENGAJA sama dengan id sistem lama (1,2,3,4,7) —
 * persuratan:import-legacy memakai id_perusahaan lama apa adanya.
 *
 * Baris dengan kode_surat yang sama tapi id berbeda dihapus dulu (biasanya sisa percobaan); kalau baris itu
 * sudah dipakai unit/surat, FK menolak dan seeder berhenti — sengaja, jangan menimpa data yang terpakai.
 *
 * Syifa Global Med (5) & Syifa Global Lestari (6) SENGAJA tidak ada: di sistem lama belum punya kode
 * surat (generate nomor mereka gagal) — tambah lewat panel /surat/perusahaans kalau kodenya sudah ada
 * (id baru otomatis; kalau mau ikut id lama, isi lewat seeder ini).
 * `hris_company_id` diisi kalau nama persis sama dengan m_companies, selain itu null (tautkan manual).
 *
 * Jalankan: php artisan db:seed --class=PersuratanPerusahaanSeeder
 */
class PersuratanPerusahaanSeeder extends Seeder
{
    /** id lama => [kode_surat, nama] */
    private const DATA = [
        1 => ['SGG', 'PT Syifa Global Group'],
        2 => ['RSUSM', 'RSU Syifa Medika Banjarbaru'],
        3 => ['RSUSM-BRB', 'RSU Syifa Medika Barabai'],
        4 => ['KOP-SMP', 'Koperasi Syifa Medika Persada'],
        7 => ['SMP', 'PT Syifa Medika Persada'],
    ];

    public function run(): void
    {
        foreach (self::DATA as $id => [$kodeSurat, $nama]) {
            Perusahaan::where('kode_surat', $kodeSurat)->where('id', '!=', $id)->delete();

            Perusahaan::updateOrCreate(
                ['id' => $id],
                [
                    'kode_surat' => $kodeSurat,
                    'nama' => $nama,
                    'hris_company_id' => Companies::where('name', $nama)->value('id'),
                    'is_active' => true,
                ],
            );
        }
    }
}
