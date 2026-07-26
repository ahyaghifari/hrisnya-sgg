<?php

namespace App\Enum;

use Filament\Support\Contracts\HasLabel;

enum CounselingStatus: string implements HasLabel
{
    case PENDING = 'pending';
    case DONE = 'done';

    public function getLabel(): string
    {
        return match ($this) {
            self::PENDING => 'Menunggu Respon',
            self::DONE => 'Selesai',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::PENDING => 'gray',
            self::DONE => 'success',
        };
    }

    public function getIcon(): string
    {
        return match ($this) {
            self::PENDING => 'heroicon-o-clock',
            self::DONE => 'heroicon-o-check-circle',
        };
    }
}
