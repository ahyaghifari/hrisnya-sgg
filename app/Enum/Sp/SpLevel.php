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

    /** Level di atasnya (SP3 tetap SP3) - saran level untuk pelanggaran berulang. */
    public function next(): self
    {
        return match ($this) {
            self::SP1 => self::SP2,
            default => self::SP3,
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

    /** Buat pilihan radio-icon "Level SP" di CreateSpPage (instruksi user 2026-09-25, pola sama
     * OvertimeTypeEnum::icon() di form Lembur) - makin tinggi level, makin "berat" ikonnya.
     * Cuma pakai ikon Free (fa-solid), "octagon-exclamation"/"hexagon-exclamation" Pro-only jadi
     * dihindari. */
    public function icon(): string
    {
        return match ($this) {
            self::SP1 => 'fa-triangle-exclamation',
            self::SP2 => 'fa-circle-exclamation',
            self::SP3 => 'fa-ban',
        };
    }
}
