<?php

namespace App\Enum\Sp;

use Filament\Support\Contracts\HasLabel;

/** Keputusan HC setelah Berita Acara selesai ditandatangani: layak SP atau tidak. */
enum SpReportDecision: string implements HasLabel
{
    case TIDAK_LAYAK_SP = 'tidak_layak_sp';
    case LANJUT_SP = 'lanjut_sp';

    public function getLabel(): string
    {
        return match ($this) {
            self::TIDAK_LAYAK_SP => 'Tidak Layak SP',
            self::LANJUT_SP => 'Lanjut ke SP',
        };
    }
}
