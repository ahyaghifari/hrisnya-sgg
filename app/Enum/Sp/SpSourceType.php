<?php

namespace App\Enum\Sp;

use Filament\Support\Contracts\HasLabel;

/**
 * Asal rekomendasi/penerbitan SP (planning/2026-09-23-sp-dan-merit-point.md §2.3). ATTENDANCE
 * ditunda total, ditambahkan nanti kalau fase attendance-trigger dikerjakan (§5).
 */
enum SpSourceType: string implements HasLabel
{
    case COACHING = 'coaching';
    case MANUAL = 'manual';

    public function getLabel(): string
    {
        return match ($this) {
            self::COACHING => 'Dari Coaching',
            self::MANUAL => 'Manual (HR)',
        };
    }
}
