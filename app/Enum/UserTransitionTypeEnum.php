<?php

namespace App\Enum;

enum UserTransitionTypeEnum: string
{
    case PROMOTION = 'promotion';
    case DEMOTION = 'demotion';
    case MUTATION = 'mutation';
    case TERMINATION = 'termination';

    /**
     * Jenis transisi yang boleh dipilih manual di form "Perubahan Status Karyawan" — cuma 4:
     * Promosi, Demosi, Mutasi Internal, PHK.
     *
     * @return array<int, self>
     */
    public static function assignable(): array
    {
        return self::cases();
    }

    public function label(): string
    {
        return match ($this) {
            self::PROMOTION => 'Promosi',
            self::DEMOTION => 'Demosi',
            self::MUTATION => 'Mutasi Internal',
            self::TERMINATION => 'PHK',
        };
    }

    public function prefix(): string
    {
        return match ($this) {
            self::PROMOTION   => 'PRO',
            self::DEMOTION    => 'DEM',
            self::MUTATION    => 'MUT',
            self::TERMINATION => 'TER',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::PROMOTION   => 'success',
            self::DEMOTION    => 'warning',
            self::MUTATION    => 'info',
            self::TERMINATION => 'danger',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::PROMOTION   => 'heroicon-o-arrow-up',
            self::DEMOTION    => 'heroicon-o-arrow-down',
            self::MUTATION    => 'heroicon-o-arrows-right-left',
            self::TERMINATION => 'heroicon-o-trash',
        };
    }
}
