<?php

namespace App\Enum\Recruitment;

use Filament\Support\Contracts\HasLabel;
use Filament\Support\Icons\Heroicon;

enum RecruitmentProcessStatus: string implements HasLabel
{
    case ASSIGNED = 'assigned';
    case SUBMITTED = 'submitted';
    case COMPLETED = 'completed';
    case ACCEPTED = 'accepted';
    case REJECTED = 'rejected';
    case REQUEST_RESCHEDULED = 'request_rescheduled';
    case RESCHEDULED = 'rescheduled';
    case FAILED = 'failed';

    public function getLabel(): string
    {
        return match ($this) {
            self::ASSIGNED => 'Assigned',
            self::SUBMITTED => 'Submitted',
            self::COMPLETED => 'Completed',
            self::ACCEPTED => 'Accepted',
            self::REJECTED => 'Rejected',
            self::REQUEST_RESCHEDULED => 'Request Rescheduled',
            self::RESCHEDULED => 'Rescheduled',
            self::FAILED => 'Failed',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::ASSIGNED => 'warning',
            self::SUBMITTED => 'info',
            self::COMPLETED => 'success',
            self::ACCEPTED => 'success',
            self::REJECTED => 'danger',
            self::REQUEST_RESCHEDULED => 'warning',
            self::RESCHEDULED => 'primary',
            self::FAILED => 'danger',   
        };
    }

    public function getIcon(): string
    {
        return match ($this) {
            self::ASSIGNED => 'heroicon-o-clipboard-document-check',
            self::SUBMITTED => 'heroicon-o-paper-airplane',
            self::COMPLETED => 'heroicon-o-check-circle',
            self::ACCEPTED => 'heroicon-o-hand-thumb-up',
            self::REJECTED => 'heroicon-o-hand-thumb-down',
            self::REQUEST_RESCHEDULED => 'heroicon-o-clock',
            self::RESCHEDULED => 'heroicon-o-calendar-days',
            self::FAILED => 'heroicon-o-x-circle',
        };
    }
}
