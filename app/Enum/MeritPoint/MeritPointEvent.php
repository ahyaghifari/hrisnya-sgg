<?php

namespace App\Enum\MeritPoint;

use Filament\Support\Contracts\HasLabel;

/**
 * Kejadian sistem yang bisa memicu rule Merit Point bertipe AUTOMATIC. Poinnya tetap diatur di
 * rule (master), bukan di sini. Ditambah saat ada kejadian baru yang mau disambungkan.
 */
enum MeritPointEvent: string implements HasLabel
{
    case LATE = 'late';
    case SP1 = 'sp1';
    case SP2 = 'sp2';
    case SP3 = 'sp3';

    public function getLabel(): string
    {
        return match ($this) {
            self::LATE => 'Terlambat (per kejadian absensi)',
            self::SP1 => 'Penerbitan SP 1',
            self::SP2 => 'Penerbitan SP 2',
            self::SP3 => 'Penerbitan SP 3',
        };
    }
}
