<?php

namespace App\Enum\Task;

use Filament\Support\Contracts\HasLabel;

enum TaskStatus: string implements HasLabel
{
    case PENDING = 'pending';
    case TODO = 'todo';
    case IN_PROGRESS = 'in_progress';
    case REVIEW = 'review';
    case REJECTED = 'rejected';
    case COMPLETE = 'complete';

    /** Urutan kolom board Tugas — PENDING & REJECTED tidak movable lewat drag, cuma via approve/reject. */
    public const BOARD_ORDER = [self::TODO, self::IN_PROGRESS, self::REVIEW, self::COMPLETE];

    public function getLabel(): string
    {
        return match ($this) {
            self::PENDING => 'Tertunda',
            self::TODO => 'Belum Dikerjakan',
            self::IN_PROGRESS => 'Sedang Dalam Proses',
            self::REVIEW => 'Ditinjau',
            self::REJECTED => 'Ditolak',
            self::COMPLETE => 'Selesai',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::PENDING => 'gray',
            self::TODO => 'gray',
            self::IN_PROGRESS => 'warning',
            self::REVIEW => 'info',
            self::REJECTED => 'danger',
            self::COMPLETE => 'success',
        };
    }

    public function getIcon(): string
    {
        return match ($this) {
            self::PENDING => 'heroicon-o-clock',
            self::TODO => 'heroicon-o-inbox',
            self::IN_PROGRESS => 'heroicon-o-arrow-path',
            self::REVIEW => 'heroicon-o-eye',
            self::REJECTED => 'heroicon-o-x-circle',
            self::COMPLETE => 'heroicon-o-check-circle',
        };
    }
}
