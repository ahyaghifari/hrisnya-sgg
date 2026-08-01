<?php

namespace App\Enum\Kepegawaian;

use App\Enum\SettingTypeEnum;

/**
 * Vocabulary tertutup untuk key `settings` module `kepegawaian` - pola sama
 * App\Enum\Attendance\AttendanceSettingEnum. Company-scoped, dibaca lewat
 * Setting::resolve($case->value, $companyId, default: ...). Dipakai UserTransitionService
 * buat hitung otomatis contract_end_date (Pengangkatan Orientasi & Perpanjangan Kontrak)
 * dan guard usia pensiun.
 */
enum KepegawaianSettingEnum: string
{
    case MAX_KONTRAK_TAHUN = 'kepegawaian_max_kontrak_tahun';
    case PERPANJANGAN_KONTRAK_BULAN = 'kepegawaian_perpanjangan_kontrak_bulan';
    case USIA_PENSIUN_TAHUN = 'kepegawaian_usia_pensiun_tahun';
    case HARI_KERJA_EFEKTIF_PER_BULAN = 'kepegawaian_hari_kerja_efektif_per_bulan';

    public function label(): string
    {
        return match ($this) {
            self::MAX_KONTRAK_TAHUN => 'Maksimal Masa Kerja Kontrak (Tahun)',
            self::PERPANJANGAN_KONTRAK_BULAN => 'Durasi per Perpanjangan Kontrak (Bulan)',
            self::USIA_PENSIUN_TAHUN => 'Usia Pensiun (Tahun)',
            self::HARI_KERJA_EFEKTIF_PER_BULAN => 'Hari Kerja Efektif per Bulan',
        };
    }

    public function helperText(): string
    {
        return match ($this) {
            self::MAX_KONTRAK_TAHUN => 'Total lama maksimal karyawan boleh berstatus Kontrak, dihitung dari Tanggal Bergabung. Pengajuan Perubahan Status/Perpanjangan Kontrak yang bikin tanggal kontrak lewat batas ini akan ditolak sistem. 0 = tidak dibatasi.',
            self::PERPANJANGAN_KONTRAK_BULAN => 'Setiap kali status berubah jadi Kontrak atau kontrak diperpanjang, tanggal berakhir kontrak otomatis dihitung sekian bulan ke depan dari tanggal efektif/tanggal kontrak sebelumnya - tidak diisi manual lagi.',
            self::USIA_PENSIUN_TAHUN => 'Usia maksimal karyawan boleh aktif bekerja. Pengajuan yang bikin karyawan melewati usia ini sebelum tanggal efektif akan ditolak sistem. 0 = tidak dibatasi.',
            self::HARI_KERJA_EFEKTIF_PER_BULAN => 'Dipakai sebagai pembagi tetap buat prorate Gaji Pokok karyawan yang Tanggal Bergabungnya jatuh di tengah periode gaji (bulan pertama): (hari kerja efektif sejak Tanggal Bergabung s.d. akhir periode / angka ini) x Gaji Pokok. Bukan dihitung dari jadwal - langsung angka yang diisi di sini.',
        };
    }

    public function type(): SettingTypeEnum
    {
        return SettingTypeEnum::INTEGER;
    }

    public function default(): string
    {
        return match ($this) {
            self::MAX_KONTRAK_TAHUN => '3',
            self::PERPANJANGAN_KONTRAK_BULAN => '12',
            self::USIA_PENSIUN_TAHUN => '55',
            self::HARI_KERJA_EFEKTIF_PER_BULAN => '25',
        };
    }
}
