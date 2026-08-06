<?php

namespace Database\Seeders;

use App\Models\Master\Companies;
use App\Models\Setting;
use Illuminate\Database\Seeder;

/**
 * Baris awal `settings` buat payroll — daftar key dari
 * knowledge/markdown/analisis-payroll-5-klasifikasi-enum-hardcode-config.md §2.1, semua
 * global (company_id NULL) sebagai default, boleh di-override per company lewat UI
 * (/keuangan/pengaturan) belakangan.
 *
 * Sengaja TIDAK dipanggil dari DatabaseSeeder — dijalankan manual, idempotent lewat
 * firstOrCreate (key yang sudah ada tidak ditimpa nilainya, cuma dipastikan barisnya ada):
 *   php artisan db:seed --class=PayrollSettingsSeeder
 */
class PayrollSettingsSeeder extends Seeder
{
    public function run(): void
    {
        $rows = [
            ['key' => 'gaji_pokok_kenaikan_per_kelas', 'module' => 'payroll', 'type' => 'decimal', 'value' => '8', 'label' => 'Kenaikan Gaji Pokok per Kelas (%)', 'description' => 'Persentase kenaikan Gaji Pokok tiap naik 1 kelas dari "Kelas Mulai" pangkatnya (lihat halaman Mapping Komponen Gaji), mentok di "Kelas Maksimal" pangkat itu. Berlaku sama untuk semua pangkat di company ini — beda company boleh beda persen.'],
            ['key' => 'prorate_metode', 'module' => 'payroll', 'type' => 'string', 'value' => 'kalender', 'label' => 'Metode Pembagi Prorate / Rate Harian', 'description' => 'Setting umum per company (setara UMP) - SATU pembagi yang dipakai di SELURUH proses "nominal per hari": prorate karyawan baru/orientasi bulan pertama (Gaji Pokok, Tunjangan Jabatan, Tunjangan Transportasi, Tunjangan Masa Kerja) MAUPUN rate harian Izin Seharian (metode Keseluruhan & Gaji Pokok). "Kalender" = dibagi jumlah hari kalender di bulan yang relevan (28-31, tergantung bulannya). "Hari Kerja Efektif" = dibagi angka tetap dari Pengaturan Kepegawaian (Hari Kerja Efektif per Bulan).'],
            ['key' => 'ump', 'module' => 'payroll', 'type' => 'decimal', 'value' => '0', 'label' => 'UMP (Upah Minimum Provinsi)', 'description' => 'Basis potongan BPJS untuk pangkat Pelaksana. Isi per company, jangan andalkan baris global ini.'],
            ['key' => 'bpjs_kesehatan_rate', 'module' => 'payroll', 'type' => 'decimal', 'value' => '1', 'label' => 'Rate BPJS Kesehatan (%)', 'description' => 'Persentase potongan BPJS Kesehatan dari basis (UMP atau nilai perusahaan).'],
            ['key' => 'bpjs_ketenagakerjaan_rate', 'module' => 'payroll', 'type' => 'decimal', 'value' => '2', 'label' => 'Rate BPJS Ketenagakerjaan (%)', 'description' => 'Persentase potongan BPJS Ketenagakerjaan dari basis.'],
            ['key' => 'bpjs_declared_value', 'module' => 'payroll', 'type' => 'decimal', 'value' => '0', 'label' => 'Nilai Basis BPJS (Pangkat Nilai Tetap)', 'description' => 'Basis potongan BPJS untuk pangkat yang di-mapping "Nilai Tetap Perusahaan" (lihat halaman Komponen Gaji, mapping per pangkat). Pangkat yang belum di-mapping otomatis tidak kena potongan BPJS.'],
            ['key' => 'keterlambatan_threshold_menit', 'module' => 'payroll', 'type' => 'integer', 'value' => '5', 'label' => 'Batas Toleransi Terlambat (menit)', 'description' => 'Terlambat lebih dari sekian menit baru dihitung kejadian keterlambatan.'],
            ['key' => 'keterlambatan_denda', 'module' => 'payroll', 'type' => 'decimal', 'value' => '25000', 'label' => 'Denda Keterlambatan', 'description' => 'Potongan per kejadian terlambat (setelah dikurangi kompensasi/grace).'],
            ['key' => 'keterlambatan_grace_count', 'module' => 'payroll', 'type' => 'integer', 'value' => '0', 'label' => 'Kompensasi Terlambat (kali/bulan)', 'description' => 'Jumlah keterlambatan pertama dalam 1 bulan yang tidak kena denda.'],
            ['key' => 'izin_potongan_flat', 'module' => 'payroll', 'type' => 'decimal', 'value' => '10000', 'label' => 'Potongan Izin Terlambat Masuk/Pulang Cepat', 'description' => 'Potongan flat per kejadian, tanpa kompensasi/grace.'],
            ['key' => 'pajak_jasa_dokter_rate', 'module' => 'payroll', 'type' => 'decimal', 'value' => '0', 'label' => 'Rate Pajak Jasa Dokter (%)', 'description' => 'Persentase potongan pajak dari total klaim fee (jasa dokter, dst) per periode.'],
            ['key' => 'insentif_metode', 'module' => 'payroll', 'type' => 'string', 'value' => 'master', 'label' => 'Metode Insentif', 'description' => '"Master" = input manual lewat tabel Insentif (dipakai Holding). "Otomatis" = dihitung dari selisih matriks Tunjangan Jabatan Lama vs Baru sesuai pangkat/kelas karyawan saat itu, tanpa input manual per orang.'],
            ['key' => 'izin_seharian_metode', 'module' => 'payroll', 'type' => 'string', 'value' => 'keseluruhan', 'label' => 'Metode Izin Seharian', 'description' => '"Keseluruhan" = potongan dari total gross salary (semua earning) dibagi hari dalam bulan, dikali jumlah izin. "Gaji Pokok" = potongan dari Gaji Pokok saja: (Gaji Pokok / Hari Kerja Efektif per Bulan, lihat Pengaturan Kepegawaian) x Persen Potongan, dikali jumlah izin.'],
            ['key' => 'izin_seharian_persen_gaji_pokok', 'module' => 'payroll', 'type' => 'decimal', 'value' => '70', 'label' => 'Persen Potongan Izin Seharian (Gaji Pokok)', 'description' => 'Dipakai kalau Metode Izin Seharian = "Gaji Pokok". Persentase dari rate harian Gaji Pokok yang dipotong per hari izin.'],
            ['key' => 'bon_metode', 'module' => 'payroll', 'type' => 'string', 'value' => 'simple', 'label' => 'Metode Bon', 'description' => '"Simple" = 1 baris nominal per karyawan per periode (seperti sekarang). "Detail" = boleh banyak baris rincian potongan (mis. "Bon tanggal 5", "Ganti kerugian barang") yang dijumlah otomatis jadi 1 komponen Bon di slip gaji.'],
        ];

        // Setting module `attendance` (izin_max_per_periode, cuti_tahunan_max_per_bulan, dst)
        // dipindah ke AttendanceSettingsSeeder — lihat
        // planning/2026-07-19-revisi-absensi-cutoff-kuota-izin-widget-pimpinan.md §5.

        foreach ($rows as $row) {
            Setting::firstOrCreate(
                ['company_id' => null, 'key' => $row['key']],
                [
                    'module' => $row['module'],
                    'type' => $row['type'],
                    'value' => $row['value'],
                    'label' => $row['label'],
                    'description' => $row['description'],
                ]
            );
        }

        // Kompensasi keterlambatan (1x gratis/bulan) cuma dikonfirmasi buat Holding — override
        // company-spesifik ini di-seed langsung (bukan lewat UI, yang sengaja belum dibangun).
        $holdingId = Companies::where('code', '0000')->value('id');

        if ($holdingId) {
            Setting::updateOrCreate(
                ['company_id' => $holdingId, 'key' => 'keterlambatan_grace_count'],
                [
                    'module' => 'payroll',
                    'type' => 'integer',
                    'value' => '1',
                    'label' => 'Kompensasi Terlambat (kali/bulan)',
                    'description' => 'Override khusus Holding: telat pertama dalam 1 bulan tidak kena denda.',
                ]
            );
        }
    }
}
