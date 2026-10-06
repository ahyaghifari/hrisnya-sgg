<?php

namespace App\Enum\Sp;

use Filament\Support\Contracts\HasLabel;

/**
 * Status alur Berita Acara (instruksi user 2026-10-01: BA jadi proses utama, SP baru terbit
 * setelah BA selesai). BA lama (status NULL) = bagian dari SP, alur lama - lihat SpReport::isLegacy().
 */
enum SpReportStatus: string implements HasLabel
{
    case DRAFT = 'draft';
    case MENUNGGU_KLARIFIKASI = 'menunggu_klarifikasi';
    case KLARIFIKASI_DIAJUKAN = 'klarifikasi_diajukan';
    case MENUNGGU_TTD = 'menunggu_ttd';
    case SELESAI = 'selesai';

    public function getLabel(): string
    {
        return match ($this) {
            self::DRAFT => 'Draft',
            self::MENUNGGU_KLARIFIKASI => 'Menyusun Klarifikasi',
            self::KLARIFIKASI_DIAJUKAN => 'Klarifikasi Diajukan',
            self::MENUNGGU_TTD => 'Menunggu Tanda Tangan',
            self::SELESAI => 'Selesai',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::DRAFT => 'gray',
            self::MENUNGGU_KLARIFIKASI, self::KLARIFIKASI_DIAJUKAN => 'warning',
            self::MENUNGGU_TTD => 'info',
            self::SELESAI => 'success',
        };
    }
}
