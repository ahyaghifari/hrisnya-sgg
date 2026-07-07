<?php

namespace App\Enum\Task;

use Filament\Support\Contracts\HasLabel;

enum TaskStatus: string implements HasLabel
{
    case PENDING = 'pending';
    case IN_PROGRESS = 'in_progress';
    case REJECTED = 'rejected';
    case COMPLETE = 'complete';

    public function getLabel(): string
    {
        return match ($this) {
            self::PENDING => 'Tertunda',
            self::IN_PROGRESS => 'Sedang Dalam Proses',
            self::REJECTED => 'Ditolak',
            self::COMPLETE => 'Selesai',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::PENDING => 'gray',
            self::IN_PROGRESS => 'warning',
            self::REJECTED => 'danger',
            self::COMPLETE => 'success',
        };
    }

    public function getIcon(): string
    {
        return match ($this) {
            self::PENDING => 'heroicon-o-clock',
            self::IN_PROGRESS => 'heroicon-o-arrow-path',
            self::REJECTED => 'heroicon-o-x-circle',
            self::COMPLETE => 'heroicon-o-check-circle',
        };
    }
}
