<?php

namespace App\Enum\Attendance;

use App\Enum\SettingTypeEnum;

/**
 * Vocabulary tertutup untuk key `settings` module `attendance` — pola sama
 * App\Enum\Payroll\GlobalPayrollSettingEnum. Semua company-scoped (tidak ada yang "selalu
 * global"), dibaca lewat Setting::resolve($case->value, $companyId, default: ...).
 */
enum AttendanceSettingEnum: string
{
    case PERIOD_START_DAY = 'attendance_period_start_day';
    case PERIOD_END_DAY = 'attendance_period_end_day';
    case CUTI_TAHUNAN_MAX_PER_BULAN = 'cuti_tahunan_max_per_bulan';
    case IZIN_MAX_PER_PERIODE_JAM_KERJA = 'izin_max_per_periode_jam_kerja';
    case IZIN_MAX_PER_PERIODE_SEHARIAN = 'izin_max_per_periode_seharian';
    case CUTI_MIN_MASA_KERJA_BULAN = 'cuti_min_masa_kerja_bulan';

    public function label(): string
    {
        return match ($this) {
            self::PERIOD_START_DAY => 'Tanggal Awal Periode Absensi',
            self::PERIOD_END_DAY => 'Tanggal Akhir Periode Absensi',
            self::CUTI_TAHUNAN_MAX_PER_BULAN => 'Batas Cuti Tahunan per Bulan (Hari)',
            self::IZIN_MAX_PER_PERIODE_JAM_KERJA => 'Batas Izin Meninggalkan Jam Kerja per Periode (Hari)',
            self::IZIN_MAX_PER_PERIODE_SEHARIAN => 'Batas Izin Tidak Masuk Seharian per Periode (Hari)',
            self::CUTI_MIN_MASA_KERJA_BULAN => 'Minimal Masa Kerja untuk Bisa Cuti (Bulan)',
        };
    }

    public function helperText(): string
    {
        return match ($this) {
            self::PERIOD_START_DAY => 'Tanggal mulai periode absensi/izin bulanan. Contoh: 26 = periode dimulai tanggal 26.',
            self::PERIOD_END_DAY => 'Tanggal akhir periode absensi/izin bulanan. Kalau lebih kecil dari Tanggal Awal, otomatis dianggap tanggal itu di bulan berikutnya. Contoh: awal=26, akhir=25 -> periode 26 bulan ini s/d 25 bulan depan.',
            self::CUTI_TAHUNAN_MAX_PER_BULAN => 'Total hari Cuti Tahunan (akumulasi semua pengajuan) yang boleh diajukan 1 user dalam 1 bulan kalender. 0 = tidak dibatasi.',
            self::IZIN_MAX_PER_PERIODE_JAM_KERJA => 'Jumlah maksimum pengajuan Izin Meninggalkan Jam Kerja (Terlambat Masuk, Pulang Cepat, Meninggalkan Jam Kerja Tengah Hari — digabung jadi 1 kuota) dalam 1 periode. 0 = tidak dibatasi.',
            self::IZIN_MAX_PER_PERIODE_SEHARIAN => 'Jumlah maksimum pengajuan Izin Tidak Masuk Seharian dalam 1 periode. 0 = tidak dibatasi.',
            self::CUTI_MIN_MASA_KERJA_BULAN => 'Karyawan baru boleh mengajukan cuti (jenis apa pun) setelah bekerja minimal sekian bulan, dihitung dari Tanggal Bergabung. 0 = tidak dibatasi, boleh cuti sejak hari pertama.',
        };
    }

    public function type(): SettingTypeEnum
    {
        return SettingTypeEnum::INTEGER;
    }

    public function default(): string
    {
        return match ($this) {
            self::PERIOD_START_DAY => '1',
            self::PERIOD_END_DAY => '31',
            self::CUTI_TAHUNAN_MAX_PER_BULAN => '0',
            self::IZIN_MAX_PER_PERIODE_JAM_KERJA => '2',
            self::IZIN_MAX_PER_PERIODE_SEHARIAN => '2',
            self::CUTI_MIN_MASA_KERJA_BULAN => '0',
        };
    }
}
