<?php

namespace App\Enum;

use Filament\Support\Contracts\HasLabel;

/**
 * Keputusan penilai atas pengajuan perpanjangan kontrak. Cuma 2 — modul ini KHUSUS perpanjang.
 * Kontrak → Tetap ada di Sikartap (pengajuan terpisah).
 */
enum ContractDecisionEnum: string implements HasLabel
{
    case PERPANJANG = 'perpanjang';
    case TIDAK_PERPANJANG = 'tidak_perpanjang';

    public function getLabel(): string
    {
        return match ($this) {
            self::PERPANJANG => 'Perpanjang Kontrak',
            self::TIDAK_PERPANJANG => 'Tidak Diperpanjang',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::PERPANJANG => 'success',
            self::TIDAK_PERPANJANG => 'danger',
        };
    }
}
