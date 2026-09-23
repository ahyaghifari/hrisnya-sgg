<?php

namespace App\Enum\Sp;

use Filament\Support\Contracts\HasLabel;

/**
 * Level SP (planning/2026-09-23-sp-dan-merit-point.md §2). SP3 selalu end_date NULL (tidak
 * pernah expired), durasi SP1/SP2 dibaca dari PembinaanSettingEnum.
 */
enum SpLevel: string implements HasLabel
{
    case SP1 = 'sp1';
    case SP2 = 'sp2';
    case SP3 = 'sp3';

    public function getLabel(): string
    {
        return match ($this) {
            self::SP1 => 'SP 1',
            self::SP2 => 'SP 2',
            self::SP3 => 'SP 3',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::SP1 => 'warning',
            self::SP2 => 'orange',
            self::SP3 => 'danger',
        };
    }
}
