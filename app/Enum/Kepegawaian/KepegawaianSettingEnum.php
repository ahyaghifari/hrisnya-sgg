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
    case PENGAJUAN_ORIENTASI_LEAD_HARI = 'kepegawaian_pengajuan_orientasi_lead_hari';
    case PENGAJUAN_KONTRAK_LEAD_HARI = 'kepegawaian_pengajuan_kontrak_lead_hari';
    case MASA_KERJA_MINIMAL_PENGANGKATAN_BULAN = 'kepegawaian_masa_kerja_minimal_pengangkatan_bulan';
    case PENGANGKATAN_MODE_BUKA_TUTUP = 'kepegawaian_pengangkatan_mode_buka_tutup';
    case PENGANGKATAN_DIBUKA = 'kepegawaian_pengangkatan_dibuka';

    public function label(): string
    {
        return match ($this) {
            self::MAX_KONTRAK_TAHUN => 'Maksimal Masa Kerja Kontrak (Tahun)',
            self::PERPANJANGAN_KONTRAK_BULAN => 'Durasi per Perpanjangan Kontrak (Bulan)',
            self::USIA_PENSIUN_TAHUN => 'Usia Pensiun (Tahun)',
            self::HARI_KERJA_EFEKTIF_PER_BULAN => 'Hari Kerja Efektif per Bulan',
            self::PENGAJUAN_ORIENTASI_LEAD_HARI => 'Pengajuan Orientasi Dibuka (Hari sebelum periode berakhir)',
            self::PENGAJUAN_KONTRAK_LEAD_HARI => 'Pengajuan Kontrak Dibuka (Hari sebelum kontrak berakhir)',
            self::MASA_KERJA_MINIMAL_PENGANGKATAN_BULAN => 'Masa Kerja Minimal Pengajuan Pengangkatan Tetap (Bulan)',
            self::PENGANGKATAN_MODE_BUKA_TUTUP => 'Mode Pengajuan Karyawan Tetap',
            self::PENGANGKATAN_DIBUKA => 'Status Pengajuan Karyawan Tetap',
        };
    }

    public function helperText(): string
    {
        return match ($this) {
            self::MAX_KONTRAK_TAHUN => 'Total lama maksimal karyawan boleh berstatus Kontrak, dihitung dari Tanggal Bergabung. Pengajuan Perubahan Status/Perpanjangan Kontrak yang bikin tanggal kontrak lewat batas ini akan ditolak sistem. 0 = tidak dibatasi.',
            self::PERPANJANGAN_KONTRAK_BULAN => 'Setiap kali status berubah jadi Kontrak atau kontrak diperpanjang, tanggal berakhir kontrak otomatis dihitung sekian bulan ke depan dari tanggal efektif/tanggal kontrak sebelumnya - tidak diisi manual lagi.',
            self::USIA_PENSIUN_TAHUN => 'Usia maksimal karyawan boleh aktif bekerja. Pengajuan yang bikin karyawan melewati usia ini sebelum tanggal efektif akan ditolak sistem. 0 = tidak dibatasi.',
            self::HARI_KERJA_EFEKTIF_PER_BULAN => 'Dipakai sebagai pembagi tetap buat prorate Gaji Pokok karyawan yang Tanggal Bergabungnya jatuh di tengah periode gaji (bulan pertama): (hari kerja efektif sejak Tanggal Bergabung s.d. akhir periode / angka ini) x Gaji Pokok. Bukan dihitung dari jadwal - langsung angka yang diisi di sini.',
            self::PENGAJUAN_ORIENTASI_LEAD_HARI => 'Karyawan orientasi baru bisa membuat pengajuan penilaian orientasi mulai sekian hari sebelum periode orientasinya berakhir. 0 = kapan saja selama periode berjalan.',
            self::PENGAJUAN_KONTRAK_LEAD_HARI => 'Ambang berapa hari sebelum kontrak berakhir pengajuan perpanjangan/perubahan kontrak boleh dibuat. 0 = kapan saja. (Dipakai modul kontrak.)',
            self::MASA_KERJA_MINIMAL_PENGANGKATAN_BULAN => 'Masa kerja minimal karyawan Kontrak sebelum boleh mengajukan jadi Tetap. Hanya dipakai kalau mode di bawah "Berdasarkan Masa Kerja". 0 = kapan saja.',
            self::PENGANGKATAN_MODE_BUKA_TUTUP => 'Berdasarkan Masa Kerja = pakai ambang di atas. Buka/Tutup Manual = diatur langsung dari Dashboard Sikartap.',
            self::PENGANGKATAN_DIBUKA => 'Diatur dari Dashboard Sikartap, bukan di sini.',
        };
    }

    public function type(): SettingTypeEnum
    {
        if ($this === self::PENGANGKATAN_DIBUKA) {
            return SettingTypeEnum::BOOLEAN;
        }

        return $this === self::PENGANGKATAN_MODE_BUKA_TUTUP ? SettingTypeEnum::STRING : SettingTypeEnum::INTEGER;
    }

    /** Pilihan dropdown - null berarti case ini bukan select (integer/boolean biasa). Dipakai
     * EmploymentSettingsPage buat render <select> alih-alih checkbox/number. */
    public function selectOptions(): ?array
    {
        return $this === self::PENGANGKATAN_MODE_BUKA_TUTUP
            ? ['masa_kerja' => 'Berdasarkan Masa Kerja', 'buka_tutup' => 'Buka/Tutup Manual']
            : null;
    }

    public function default(): string
    {
        return match ($this) {
            self::MAX_KONTRAK_TAHUN => '3',
            self::PERPANJANGAN_KONTRAK_BULAN => '12',
            self::USIA_PENSIUN_TAHUN => '55',
            self::HARI_KERJA_EFEKTIF_PER_BULAN => '25',
            self::PENGAJUAN_ORIENTASI_LEAD_HARI => '30',
            self::PENGAJUAN_KONTRAK_LEAD_HARI => '30',
            self::MASA_KERJA_MINIMAL_PENGANGKATAN_BULAN => '15',
            self::PENGANGKATAN_MODE_BUKA_TUTUP => 'masa_kerja',
            self::PENGANGKATAN_DIBUKA => 'false',
        };
    }
}
