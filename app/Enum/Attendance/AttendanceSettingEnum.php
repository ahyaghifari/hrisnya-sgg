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
    case IZIN_MENINGGALKAN_JAM_KERJA_MAX_JAM = 'izin_meninggalkan_jam_kerja_max_jam';
    case CUTI_MIN_MASA_KERJA_BULAN = 'cuti_min_masa_kerja_bulan';
    case TUKAR_SHIFT_MAX_PER_BULAN = 'tukar_shift_max_per_bulan';
    case TUKAR_SHIFT_MIN_LEAD_DAYS = 'tukar_shift_min_lead_days';

    public function label(): string
    {
        return match ($this) {
            self::PERIOD_START_DAY => 'Tanggal Awal Periode Absensi',
            self::PERIOD_END_DAY => 'Tanggal Akhir Periode Absensi',
            self::CUTI_TAHUNAN_MAX_PER_BULAN => 'Batas Cuti Tahunan per Bulan (Hari)',
            self::IZIN_MAX_PER_PERIODE_JAM_KERJA => 'Batas Izin Meninggalkan Jam Kerja per Periode (Hari)',
            self::IZIN_MAX_PER_PERIODE_SEHARIAN => 'Batas Izin Tidak Masuk Seharian per Periode (Hari)',
            self::IZIN_MENINGGALKAN_JAM_KERJA_MAX_JAM => 'Maksimal Jam Izin Meninggalkan Jam Kerja',
            self::CUTI_MIN_MASA_KERJA_BULAN => 'Minimal Masa Kerja untuk Bisa Cuti (Bulan)',
            self::TUKAR_SHIFT_MAX_PER_BULAN => 'Batas Tukar Shift per Bulan (Kali)',
            self::TUKAR_SHIFT_MIN_LEAD_DAYS => 'Minimal Jarak Hari Pengajuan Tukar Shift',
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
            self::IZIN_MENINGGALKAN_JAM_KERJA_MAX_JAM => 'Durasi maksimum (jam) 1 pengajuan Izin Meninggalkan Jam Kerja — berlaku sama untuk semua sub-jenisnya (Terlambat Masuk, Pulang Cepat, Meninggalkan Jam Kerja Tengah Hari). 0 = tidak dibatasi.',
            self::CUTI_MIN_MASA_KERJA_BULAN => 'Karyawan baru boleh mengajukan cuti (jenis apa pun) setelah bekerja minimal sekian bulan, dihitung dari Tanggal Bergabung. 0 = tidak dibatasi, boleh cuti sejak hari pertama.',
            self::TUKAR_SHIFT_MAX_PER_BULAN => 'Jumlah maksimum pengajuan tukar shift yang boleh diajukan 1 karyawan dalam 1 bulan kalender (dihitung dari tanggal shift yang ditukar; pengajuan ditolak/dibatalkan tidak dihitung). 0 = tidak dibatasi.',
            self::TUKAR_SHIFT_MIN_LEAD_DAYS => 'Pengajuan tukar shift harus dibuat minimal sekian hari sebelum tanggal shift paling awal yang terlibat. Contoh: 2 = shift tanggal 10 paling lambat diajukan tanggal 8. 0 = tidak dibatasi.',
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
            self::IZIN_MENINGGALKAN_JAM_KERJA_MAX_JAM => '3',
            self::CUTI_MIN_MASA_KERJA_BULAN => '0',
            self::TUKAR_SHIFT_MAX_PER_BULAN => '0',
            self::TUKAR_SHIFT_MIN_LEAD_DAYS => '0',
        };
    }
}
