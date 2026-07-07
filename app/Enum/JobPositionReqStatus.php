<?php

namespace App\Enum;

use Filament\Support\Contracts\HasLabel;

enum JobPositionReqStatus: string implements HasLabel
{
    case PENDING = 'pending';
    case DRAFT = 'draft';
    case REQUESTED = 'requested';
    case APPROVED = 'approved';
    case REJECTED = 'rejected';

    public function label(): string
    {
        return match($this) {
            self::PENDING => 'Pending',
            self::DRAFT => 'Draft',
            self::REQUESTED => 'Requested',
            self::APPROVED => 'Approved',
            self::REJECTED => 'Rejected',
        };
    }

    public function getLabel(): string
    {
        return $this->label();
    }
}
