<?php

namespace App\Enum\MeritPoint;

use Filament\Support\Contracts\HasLabel;

/**
 * Cara rule dipakai (planning/2026-09-23-sp-dan-merit-point.md §3.2, §3.4) - masih berstatus
 * DEMO. AUTOMATIC = poin dicatat otomatis oleh MeritPointService::applyEvent() saat
 * MeritPointEvent terjadi (dipanggil dari AttendanceRecords/SpService) - rule-nya butuh kolom
 * `event` terisi. MANUAL = dipilih manual lewat modal transaksi tab Karir
 * (EmployeeStatistics::saveMeritTransaction(), izin BERI_MERIT_POINT) - selalu pakai rule
 * bertipe manual, poinnya di-snapshot dari rule saat transaksi dibuat.
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
