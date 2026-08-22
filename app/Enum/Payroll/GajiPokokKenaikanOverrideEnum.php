<?php

namespace App\Enum\Payroll;

/**
 * Override manual persen kenaikan Gaji Pokok per kelas (default company via
 * Setting::gaji_pokok_kenaikan_per_kelas, sekarang fallback 8% - lihat GajiPokokCalculator)
 * untuk user tertentu - instruksi user 2026-08-22.
 */
enum GajiPokokKenaikanOverrideEnum: string
{
    case ABDURRAHMAN_HAKIM = '01kn1dpvvfs1hdwej8k3j4rmr0';

    public function rate(): float
    {
        return match ($this) {
            self::ABDURRAHMAN_HAKIM => 16.0,
        };
    }

    public static function rateForUser(?string $userId): ?float
    {
        return $userId ? self::tryFrom($userId)?->rate() : null;
    }
}
