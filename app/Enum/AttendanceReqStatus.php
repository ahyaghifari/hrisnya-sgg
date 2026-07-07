<?php

namespace App\Enum;

use Filament\Support\Contracts\HasLabel;

enum AttendanceReqStatus: string implements HasLabel
{
    case SUBMITTED = 'submitted';
    case IN_REVIEW = 'in_review';
    case APPROVED = 'approved';
    case REJECTED = 'rejected';
    case CANCELLED = 'cancelled';
    case FAILED = 'failed';

    public function getLabel(): string
    {
        return match ($this) {
            self::SUBMITTED => 'Submitted',
            self::IN_REVIEW => 'Sedang Direview',
            self::APPROVED => 'Disetujui',
            self::REJECTED => 'Ditolak',
            self::CANCELLED => 'Dibatalkan',
            self::FAILED => 'Gagal',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::SUBMITTED => 'gray',
            self::IN_REVIEW => 'warning',
            self::APPROVED => 'success',
            self::REJECTED => 'danger',
            self::CANCELLED => 'gray',
            self::FAILED => 'danger',
        };
    }

    public function getIcon(): string
    {
        return match ($this) {
            self::SUBMITTED => 'heroicon-o-document-text',
            self::IN_REVIEW => 'heroicon-o-clock',
            self::APPROVED => 'heroicon-o-check-circle',
            self::REJECTED => 'heroicon-o-x-circle',
            self::CANCELLED => 'heroicon-o-minus-circle',
            self::FAILED => 'heroicon-o-exclamation-triangle',
        };
    } 
}
