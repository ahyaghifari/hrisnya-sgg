<?php

namespace App\Enum;

use Filament\Support\Contracts\HasLabel;

enum CourseUserStatus: string implements HasLabel
{
    case REQUESTED = "requested";
    case APPROVED = "approved";
    case IN_PROGRESS = "in_progress";
    case COMPLETED = "completed";
    case CANCELED = "canceled";

    public function getLabel(): string
    {
        return match ($this) {
            self::REQUESTED => 'Requested',
            self::APPROVED => 'Approved',
            self::IN_PROGRESS => 'In Progress',
            self::COMPLETED => 'Completed',
            self::CANCELED => 'Canceled',
        };
    }
}
