<?php

namespace App\Enum;

use Filament\Support\Contracts\HasLabel;

/**
 * Kategori Tipe Karyawan (m_employee_types.category) - dipakai antara lain untuk membatasi
 * tab di halaman Data Diri (App\Livewire\EmployeeAttendanceStatistics): karyawan ORIENTASI
 * cuma lihat tab Profil + Presensi, tanpa Absensi/Riwayat Pengajuan/Dokumen/Penggajian.
 */
enum EmployeeTypeCategory: string implements HasLabel
{
    case TETAP = 'tetap';
    case KONTRAK = 'kontrak';
    case ORIENTASI = 'orientasi';

    public function getLabel(): string
    {
        return match ($this) {
            self::TETAP => 'Tetap',
            self::KONTRAK => 'Kontrak',
            self::ORIENTASI => 'Orientasi',
        };
    }
}
