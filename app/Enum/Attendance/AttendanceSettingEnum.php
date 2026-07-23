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
    case PERIOD_CUTOFF_DAY = 'attendance_period_cutoff_day';
    case CUTI_TAHUNAN_MAX_PER_BULAN = 'cuti_tahunan_max_per_bulan';
    case IZIN_MAX_PER_PERIODE = 'izin_max_per_periode';
    case CUTI_MIN_MASA_KERJA_BULAN = 'cuti_min_masa_kerja_bulan';

    public function label(): string
    {
        return match ($this) {
            self::PERIOD_CUTOFF_DAY => 'Tanggal Cutoff Periode Absensi',
            self::CUTI_TAHUNAN_MAX_PER_BULAN => 'Batas Cuti Tahunan per Periode (Hari)',
            self::IZIN_MAX_PER_PERIODE => 'Batas Izin per Periode (Hari)',
            self::CUTI_MIN_MASA_KERJA_BULAN => 'Minimal Masa Kerja untuk Bisa Cuti (Bulan)',
        };
    }

    public function helperText(): string
    {
        return match ($this) {
            self::PERIOD_CUTOFF_DAY => 'Tanggal mulai periode absensi/cuti bulanan. 1 = ikut kalender biasa (tanggal 1 s/d akhir bulan). Contoh: 21 = periode berjalan tanggal 21 s/d tanggal 20 bulan berikutnya.',
            self::CUTI_TAHUNAN_MAX_PER_BULAN => 'Total hari Cuti Tahunan (akumulasi semua pengajuan) yang boleh diajukan 1 user dalam 1 periode. 0 = tidak dibatasi.',
            self::IZIN_MAX_PER_PERIODE => 'Jumlah maksimum pengajuan izin (Terlambat Masuk, Pulang Cepat, Meninggalkan Jam Kerja, Izin Seharian — digabung jadi 1 kuota) dalam 1 periode. 0 = tidak dibatasi.',
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
            self::PERIOD_CUTOFF_DAY => '1',
            self::CUTI_TAHUNAN_MAX_PER_BULAN => '0',
            self::IZIN_MAX_PER_PERIODE => '2',
            self::CUTI_MIN_MASA_KERJA_BULAN => '0',
        };
    }
}
