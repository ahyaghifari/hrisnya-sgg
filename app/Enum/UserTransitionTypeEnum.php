<?php

namespace App\Enum;

enum UserTransitionTypeEnum: string
{
    case PROMOTION = 'promotion';
    case DEMOTION = 'demotion';
    case MUTATION = 'mutation';
    case TERMINATION = 'termination';
    case KELAS_CHANGE = 'kelas_change';
    case EMPLOYMENT_STATUS_CHANGE = 'employment_status_change';
    case CONTRACT_EXTENSION = 'contract_extension';

    public function label(): string
    {
        return match ($this) {
            self::PROMOTION => 'Promosi',
            self::DEMOTION => 'Demosi',
            self::MUTATION => 'Mutasi (Perusahaan)',
            self::TERMINATION => 'PHK',
            self::KELAS_CHANGE => 'Perubahan Kelas',
            self::EMPLOYMENT_STATUS_CHANGE => 'Perubahan Status Kepegawaian',
            self::CONTRACT_EXTENSION => 'Perpanjangan Kontrak',
        };
    }

    public function prefix(): string
    {
        return match ($this) {
            self::PROMOTION   => 'PRO',
            self::DEMOTION    => 'DEM',
            self::MUTATION    => 'MUT',
            self::TERMINATION => 'TER',
            self::KELAS_CHANGE => 'KLS',
            self::EMPLOYMENT_STATUS_CHANGE => 'STA',
            self::CONTRACT_EXTENSION => 'PPK',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::PROMOTION   => 'success',
            self::DEMOTION    => 'warning',
            self::MUTATION    => 'info',
            self::TERMINATION => 'danger',
            self::KELAS_CHANGE => 'info',
            self::EMPLOYMENT_STATUS_CHANGE => 'success',
            self::CONTRACT_EXTENSION => 'info',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::PROMOTION   => 'heroicon-o-arrow-up',
            self::DEMOTION    => 'heroicon-o-arrow-down',
            self::MUTATION    => 'heroicon-o-building-office',
            self::TERMINATION => 'heroicon-o-trash',
            self::KELAS_CHANGE => 'heroicon-o-arrow-trending-up',
            self::EMPLOYMENT_STATUS_CHANGE => 'heroicon-o-identification',
            self::CONTRACT_EXTENSION => 'heroicon-o-calendar-days',
        };
    }
}
