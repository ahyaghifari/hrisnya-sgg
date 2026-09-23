<?php

namespace App\Enum\Sp;

use Filament\Support\Contracts\HasLabel;

/**
 * Status review HR atas sp_recommendations (planning/2026-09-23-sp-dan-merit-point.md §2.2, §2.4).
 * Tidak ada job pembersih otomatis untuk yang menumpuk pending (§4, non-goal).
 */
enum SpRecommendationDecision: string implements HasLabel
{
    case PENDING = 'pending';
    case APPROVED = 'approved';
    case REJECTED = 'rejected';

    public function getLabel(): string
    {
        return match ($this) {
            self::PENDING => 'Menunggu Review',
            self::APPROVED => 'Disetujui',
            self::REJECTED => 'Ditolak',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::PENDING => 'gray',
            self::APPROVED => 'success',
            self::REJECTED => 'danger',
        };
    }
}
