<?php

namespace App\Enum;

use Filament\Support\Contracts\HasLabel;
use Illuminate\Contracts\Support\Htmlable;

enum AssessmentTemplateItemType: string implements HasLabel
{
    case MCQ = 'mcq';                         // Pilihan Ganda
    case ORIENTATION = 'orientation';         // Orientasi Pegawai Baru
    case SIMPLE_SCALE = 'simple_scale';       // Skala Penilaian 1–5

    public function getLabel(): string|Htmlable|null
    {
        return match ($this) {
            self::MCQ => 'Pilihan Ganda (MCQ)',
            self::ORIENTATION => 'Orientasi Pegawai Baru',
            self::SIMPLE_SCALE => 'Skala Penilaian (1–5)',
        };
    }
}