<?php

namespace App\Enum;

enum UserTransitionTypeEnum: string
{
    case PROMOTION = 'promotion';
    case DEMOTION = 'demotion';
    case MUTATION = 'mutation';
    case TERMINATION = 'termination';
    case KELAS_CHANGE = 'kelas_change';

    /**
     * Label "Pengangkatan Orientasi" - value/nama case sengaja TIDAK diubah (masih
     * `employment_status_change`) supaya data lama (transition_type di DB) tetap valid, cuma
     * labelnya yang diganti karena sebelumnya menyesatkan ("Perubahan Status Kepegawaian"
     * kedengarannya generik). Fungsinya SELALU khusus Orientasi naik ke Kontrak/Tetap - BUKAN
     * buat Kontrak ke Tetap (itu perlu pertimbangan lebih banyak, tidak di-cover jenis transisi
     * ini). Employee picker (CreateUserTransitionPage::getEmployeesProperty()) sudah membatasi ke
     * kategori Orientasi saja, plus guard eksplisit di submit() - lihat komentar di sana.
     */
    case EMPLOYMENT_STATUS_CHANGE = 'employment_status_change';
    case CONTRACT_EXTENSION = 'contract_extension';

    /**
     * Jenis transisi yang boleh dipilih manual di form "Perubahan Status Karyawan".
     * EMPLOYMENT_STATUS_CHANGE (Pengangkatan Orientasi) & CONTRACT_EXTENSION (Perpanjangan
     * Kontrak) sengaja DIKELUARKAN — orientasi->kontrak sekarang langsung di halaman Penilaian
     * Orientasi; modul kontrak (termasuk perpanjangan) dikerjakan terpisah. Case-nya tetap ada
     * supaya data lama & alur approval yang masih jalan tidak rusak.
     *
     * @return array<int, self>
     */
    public static function assignable(): array
    {
        return [
            self::PROMOTION,
            self::DEMOTION,
            self::MUTATION,
            self::TERMINATION,
            self::KELAS_CHANGE,
        ];
    }

    public function label(): string
    {
        return match ($this) {
            self::PROMOTION => 'Promosi',
            self::DEMOTION => 'Demosi',
            self::MUTATION => 'Mutasi (Perusahaan)',
            self::TERMINATION => 'PHK',
            self::KELAS_CHANGE => 'Perubahan Kelas',
            self::EMPLOYMENT_STATUS_CHANGE => 'Pengangkatan Orientasi',
            self::CONTRACT_EXTENSION => 'Perpanjangan Kontrak',
        };
    }

    public function prefix(): string
    {
        return match ($this) {
            self::PROMOTION   => 'PRO',
            self::DEMOTION    => 'DEM',
            self::MUTATION    => 'MUT',
            self::TERMINATION => 'TER',
            self::KELAS_CHANGE => 'KLS',
            self::EMPLOYMENT_STATUS_CHANGE => 'STA',
            self::CONTRACT_EXTENSION => 'PPK',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::PROMOTION   => 'success',
            self::DEMOTION    => 'warning',
            self::MUTATION    => 'info',
            self::TERMINATION => 'danger',
            self::KELAS_CHANGE => 'info',
            self::EMPLOYMENT_STATUS_CHANGE => 'success',
            self::CONTRACT_EXTENSION => 'info',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::PROMOTION   => 'heroicon-o-arrow-up',
            self::DEMOTION    => 'heroicon-o-arrow-down',
            self::MUTATION    => 'heroicon-o-building-office',
            self::TERMINATION => 'heroicon-o-trash',
            self::KELAS_CHANGE => 'heroicon-o-arrow-trending-up',
            self::EMPLOYMENT_STATUS_CHANGE => 'heroicon-o-identification',
            self::CONTRACT_EXTENSION => 'heroicon-o-calendar-days',
        };
    }
}
