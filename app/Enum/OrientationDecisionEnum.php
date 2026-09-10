<?php

namespace App\Enum;

use Filament\Support\Contracts\HasLabel;

/**
 * Keputusan penilai atas pengajuan penilaian orientasi. Hanya 2 (2026-09-10, instruksi user):
 * karyawan orientasi direkomendasikan lanjut ke kontrak kerja, atau hubungan kerjanya tidak
 * dilanjutkan. Tidak ada lagi opsi "perpanjang orientasi".
 */
enum OrientationDecisionEnum: string implements HasLabel
{
    case LANJUT_KONTRAK = 'lanjut_kontrak';
    case TIDAK_DILANJUTKAN = 'tidak_dilanjutkan';

    public function getLabel(): string
    {
        return match ($this) {
            self::LANJUT_KONTRAK => 'Direkomendasikan untuk Kontrak Kerja',
            self::TIDAK_DILANJUTKAN => 'Tidak Dilanjutkan',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::LANJUT_KONTRAK => 'success',
            self::TIDAK_DILANJUTKAN => 'danger',
        };
    }
}
