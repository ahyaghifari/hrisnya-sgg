<?php

namespace App\Enum;

use Filament\Support\Contracts\HasLabel;

/** Keputusan penilai atas pengajuan penilaian orientasi (diisi manual di step penilaian). */
enum OrientationDecisionEnum: string implements HasLabel
{
    case LULUS = 'lulus';
    case PERPANJANG = 'perpanjang';
    case TIDAK_LULUS = 'tidak_lulus';

    public function getLabel(): string
    {
        return match ($this) {
            self::LULUS => 'Lulus',
            self::PERPANJANG => 'Perpanjang Orientasi',
            self::TIDAK_LULUS => 'Tidak Lulus',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::LULUS => 'success',
            self::PERPANJANG => 'warning',
            self::TIDAK_LULUS => 'danger',
        };
    }
}
