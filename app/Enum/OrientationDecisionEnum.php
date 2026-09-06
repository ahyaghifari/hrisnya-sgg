<?php

namespace App\Enum;

use Filament\Support\Contracts\HasLabel;

/**
 * Keputusan penilai atas pengajuan penilaian orientasi. Hanya 2 — TIDAK ada "tidak lulus"
 * (2026-09-06, instruksi user): karyawan orientasi entah lanjut ke kontrak atau orientasinya
 * diperpanjang.
 */
enum OrientationDecisionEnum: string implements HasLabel
{
    case LANJUT_KONTRAK = 'lanjut_kontrak';
    case PERPANJANG = 'perpanjang';

    public function getLabel(): string
    {
        return match ($this) {
            self::LANJUT_KONTRAK => 'Lanjut Kontrak',
            self::PERPANJANG => 'Perpanjang Orientasi',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::LANJUT_KONTRAK => 'success',
            self::PERPANJANG => 'warning',
        };
    }
}
