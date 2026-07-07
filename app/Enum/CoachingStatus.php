<?php

namespace App\Enum;

use Filament\Support\Contracts\HasLabel;

enum CoachingStatus: string implements HasLabel
{
    case PENDING = 'pending';
    case IN_PROGRESS = 'in_progress';
    case SUBMITTED = 'submitted';
    case COMPLETED = 'completed';
    case REJECTED = 'rejected';

    public function getLabel(): string
    {
        return match ($this) {
            self::PENDING => 'Menunggu',
            self::IN_PROGRESS => 'Dalam Proses',
            self::SUBMITTED => 'Menunggu Persetujuan',
            self::COMPLETED => 'Selesai',
            self::REJECTED => 'Ditolak',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::PENDING => 'gray',
            self::IN_PROGRESS => 'warning',
            self::SUBMITTED => 'info',
            self::COMPLETED => 'success',
            self::REJECTED => 'danger',
        };
    }

    public function getIcon(): string
    {
        return match ($this) {
            self::PENDING => 'heroicon-o-clock',
            self::IN_PROGRESS => 'heroicon-o-arrow-path',
            self::SUBMITTED => 'heroicon-o-paper-airplane',
            self::COMPLETED => 'heroicon-o-check-circle',
            self::REJECTED => 'heroicon-o-x-circle',
        };
    }
}
