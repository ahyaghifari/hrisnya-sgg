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
            ['key' => 'ump', 'module' => 'payroll', 'type' => 'decimal', 'value' => '0', 'label' => 'UMP (Upah Minimum Provinsi)', 'description' => 'Basis potongan BPJS untuk pangkat Pelaksana. Isi per company, jangan andalkan baris global ini.'],
            ['key' => 'bpjs_kesehatan_rate', 'module' => 'payroll', 'type' => 'decimal', 'value' => '1', 'label' => 'Rate BPJS Kesehatan (%)', 'description' => 'Persentase potongan BPJS Kesehatan dari basis (UMP atau nilai perusahaan).'],
            ['key' => 'bpjs_ketenagakerjaan_rate', 'module' => 'payroll', 'type' => 'decimal', 'value' => '2', 'label' => 'Rate BPJS Ketenagakerjaan (%)', 'description' => 'Persentase potongan BPJS Ketenagakerjaan dari basis.'],
            ['key' => 'bpjs_declared_value', 'module' => 'payroll', 'type' => 'decimal', 'value' => '0', 'label' => 'Nilai Basis BPJS Supervisor/Manager', 'description' => 'Basis potongan BPJS untuk pangkat Supervisor & Manager. Wajib diisi per company.'],
            ['key' => 'keterlambatan_threshold_menit', 'module' => 'payroll', 'type' => 'integer', 'value' => '5', 'label' => 'Batas Toleransi Terlambat (menit)', 'description' => 'Terlambat lebih dari sekian menit baru dihitung kejadian keterlambatan.'],
            ['key' => 'keterlambatan_denda', 'module' => 'payroll', 'type' => 'decimal', 'value' => '25000', 'label' => 'Denda Keterlambatan', 'description' => 'Potongan per kejadian terlambat (setelah dikurangi kompensasi/grace).'],
            ['key' => 'keterlambatan_grace_count', 'module' => 'payroll', 'type' => 'integer', 'value' => '0', 'label' => 'Kompensasi Terlambat (kali/bulan)', 'description' => 'Jumlah keterlambatan pertama dalam 1 bulan yang tidak kena denda.'],
            ['key' => 'izin_potongan_flat', 'module' => 'payroll', 'type' => 'decimal', 'value' => '10000', 'label' => 'Potongan Izin Terlambat Masuk/Pulang Cepat', 'description' => 'Potongan flat per kejadian, tanpa kompensasi/grace.'],
            ['key' => 'lembur_minimum_jam', 'module' => 'payroll', 'type' => 'integer', 'value' => '2', 'label' => 'Minimum Jam Lembur Dibayar', 'description' => 'Lembur di bawah durasi ini tidak dihitung.'],
            ['key' => 'pajak_jasa_dokter_rate', 'module' => 'payroll', 'type' => 'decimal', 'value' => '0', 'label' => 'Rate Pajak Jasa Dokter (%)', 'description' => 'Persentase potongan pajak dari total klaim fee (jasa dokter, dst) per periode.'],
            ['key' => 'izin_max_per_periode', 'module' => 'attendance', 'type' => 'integer', 'value' => '2', 'label' => 'Batas Izin per Bulan', 'description' => 'Jumlah maksimum pengajuan izin (semua jenis digabung) yang diperbolehkan dalam 1 bulan.'],
        ];

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
