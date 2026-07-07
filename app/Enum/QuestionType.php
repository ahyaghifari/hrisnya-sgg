<?php

namespace App\Enum;

use Filament\Support\Contracts\HasLabel;

enum QuestionType: string implements HasLabel
{
    case MultipleChoice = 'multiple_choice';
    case Essay = 'essay';

    public function getLabel(): string
    {
        return match ($this) {
            self::MultipleChoice => 'Pilihan Ganda',
            self::Essay => 'Esai',
        };
    }
}
