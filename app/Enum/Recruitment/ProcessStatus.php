<?php

namespace App\Enum\Recruitment;

use Filament\Support\Contracts\HasLabel;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;

enum ProcessStatus: string implements HasLabel
{
    case SUBMITTED = 'submitted';
    case UNDER_REVIEW = 'under_review';
    case IN_SELECTION = 'in_selection';
    case OFFERED = 'offered';
    case HIRED = 'hired';
    case REJECTED = 'rejected';
    case WITHDRAWN = 'withdrawn';

    public function getLabel(): string|Htmlable|null
    {
        return match ($this) {
            self::SUBMITTED => 'Diajukan',
            self::UNDER_REVIEW => 'Dalam Review',
            self::IN_SELECTION => 'Dalam Seleksi',
            self::OFFERED => 'Offered',
            self::HIRED => 'Diterima',
            self::REJECTED => 'Ditolak',
            self::WITHDRAWN => 'Mengundurkan Diri',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::SUBMITTED => 'gray',
            self::UNDER_REVIEW => 'info',
            self::IN_SELECTION => 'info',
            self::OFFERED => 'success',
            self::HIRED => 'success',
            self::REJECTED => 'danger',
            self::WITHDRAWN => 'warning',
        };
    }

    public function getIcon(): string
    {
        return match ($this) {
            self::SUBMITTED => 'heroicon-o-document-text',
            self::UNDER_REVIEW => 'heroicon-o-eye',
            self::IN_SELECTION => 'heroicon-o-clipboard-document-list',
            self::OFFERED => 'heroicon-o-hand-raised',
            self::HIRED => 'heroicon-o-check-circle',
            self::REJECTED => 'heroicon-o-x-circle',
            self::WITHDRAWN => 'heroicon-o-exclamation-circle',
        };
    }
}
