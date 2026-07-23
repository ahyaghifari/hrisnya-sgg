<?php

namespace App\Enum\Recruitment;

enum JobOpeningStatus: string
{
    case WAITING = 'waiting';
    case OPEN = 'open';
    case CLOSED = 'closed';

    public function getLabel(): string
    {
        return match ($this) {
            self::WAITING => 'Menunggu Dibuka',
            self::OPEN => 'Sedang Dibuka',
            self::CLOSED => 'Sudah Ditutup',
        };
    }

    public function getBadgeClass(): string
    {
        return match ($this) {
            self::WAITING => 'badge-pending',
            self::OPEN => 'badge-approved',
            self::CLOSED => 'badge-rejected',
        };
    }
}
