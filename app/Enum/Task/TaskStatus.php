<?php

namespace App\Enum\Task;

use Filament\Support\Contracts\HasLabel;

enum TaskStatus: string implements HasLabel
{
    case TODO = 'todo';
    case IN_PROGRESS = 'in_progress';
    case REVIEW = 'review';
    case COMPLETE = 'complete';

    /**
     * Urutan kolom board Tugas, 4 status total (2026-08-02). Approval formal (pending/rejected)
     * bukan lagi bagian dari status kerja - itu dilacak lewat Task::approvalSnapshot->status,
     * task baru dianggap "ada" di board (assigned_to keisi) setelah disetujui.
     */
    public const BOARD_ORDER = [self::TODO, self::IN_PROGRESS, self::REVIEW, self::COMPLETE];

    /** Task self-assigned ("Todo Saya") lewatin REVIEW - langsung IN_PROGRESS -> COMPLETE. */
    public const BOARD_ORDER_SELF_ASSIGNED = [self::TODO, self::IN_PROGRESS, self::COMPLETE];

    public function getLabel(): string
    {
        return match ($this) {
            self::TODO => 'Belum Dikerjakan',
            self::IN_PROGRESS => 'Sedang Dalam Proses',
            self::REVIEW => 'Review',
            self::COMPLETE => 'Selesai',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::TODO => 'gray',
            self::IN_PROGRESS => 'warning',
            self::REVIEW => 'info',
            self::COMPLETE => 'success',
        };
    }

    public function getIcon(): string
    {
        return match ($this) {
            self::TODO => 'heroicon-o-inbox',
            self::IN_PROGRESS => 'heroicon-o-arrow-path',
            self::REVIEW => 'heroicon-o-eye',
            self::COMPLETE => 'heroicon-o-check-circle',
        };
    }
}
