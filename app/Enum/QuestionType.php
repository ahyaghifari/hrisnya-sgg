<?php

namespace App\Enum;

use Filament\Support\Contracts\HasLabel;

enum QuestionType: string implements HasLabel
{
    case MultipleChoice = 'multiple_choice';
    case Essay = 'essay';

    /**
     * Tipe yang boleh dipilih saat MEMBUAT soal baru. Esai dinonaktifkan sementara (2026-09-20):
     * belum ada UI untuk menilainya (QuizService::reviewEssayAnswers() tanpa caller), attempt
     * berisi esai macet di `pending_review`. Case Essay tetap ada supaya soal esai lama masih terbaca.
     *
     * @return list<self>
     */
    public static function creatable(): array
    {
        return [self::MultipleChoice];
    }

    public function getLabel(): string
    {
        return match ($this) {
            self::MultipleChoice => 'Pilihan Ganda',
            self::Essay => 'Esai',
        };
    }
}
