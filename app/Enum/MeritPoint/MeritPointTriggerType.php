<?php

namespace App\Enum\MeritPoint;

use Filament\Support\Contracts\HasLabel;

/**
 * Cara rule dipakai (planning/2026-09-23-sp-dan-merit-point.md §3.2, §3.4) - masih berstatus
 * DEMO. AUTOMATIC sekadar penanda skema untuk fase mendatang (trigger otomatis BELUM dibangun,
 * lihat §4 non-goals) - untuk sekarang transaksi selalu dibuat manual lewat
 * MeritPointTransactionResource, baik pakai rule (trigger_type apa pun) maupun bebas tanpa rule.
 */
enum MeritPointTriggerType: string implements HasLabel
{
    case AUTOMATIC = 'automatic';
    case MANUAL = 'manual';

    public function getLabel(): string
    {
        return match ($this) {
            self::AUTOMATIC => 'Otomatis',
            self::MANUAL => 'Manual',
        };
    }
}
