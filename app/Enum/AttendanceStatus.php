<?php

namespace App\Enum;

use Filament\Support\Contracts\HasLabel;

enum AttendanceStatus: string implements HasLabel
{
    case PRESENT  = 'present';
    case LATE     = 'late';
    case ABSENT   = 'absent';
    case OVERTIME = 'overtime';
    case LEAVE    = 'leave';
    case SICK     = 'sick';
    case PERMIT   = 'permit';

    public function getLabel(): string
    {
        return match ($this) {
            self::PRESENT  => 'Hadir',
            self::LATE     => 'Terlambat',
            self::ABSENT   => 'Tidak Hadir',
            self::OVERTIME => 'Lembur',
            self::LEAVE    => 'Cuti',
            self::SICK     => 'Sakit',
            self::PERMIT   => 'Izin',
        };
    }
}
