<?php

namespace App\Enum\MeritPoint;

use Filament\Support\Contracts\HasLabel;

/** Kategori Merit Point (planning/2026-09-23-sp-dan-merit-point.md §3.2) - masih berstatus DEMO. */
enum MeritPointType: string implements HasLabel
{
    case REWARD = 'reward';
    case PENALTY = 'penalty';
    case CONTRIBUTION = 'contribution';

    public function getLabel(): string
    {
        return match ($this) {
            self::REWARD => 'Reward',
            self::PENALTY => 'Penalty',
            self::CONTRIBUTION => 'Kontribusi',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::REWARD => 'success',
            self::PENALTY => 'danger',
            self::CONTRIBUTION => 'info',
        };
    }
}
