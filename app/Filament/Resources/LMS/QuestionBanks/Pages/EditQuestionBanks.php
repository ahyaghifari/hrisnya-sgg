<?php

namespace App\Filament\Resources\LMS\QuestionBanks\Pages;

use App\Filament\Resources\LMS\QuestionBanks\QuestionBanksResource;
use Filament\Actions\DeleteAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditQuestionBanks extends EditRecord
{
    protected static string $resource = QuestionBanksResource::class;
    protected static ?string $title = 'Edit Bank Soal';

    protected function getHeaderActions(): array
    {
        return [
            // Soal yang sudah pernah dipakai di kuis (atau punya riwayat jawaban peserta) tidak
            // boleh dihapus — FK ke lms_quiz_questions & lms_quiz_user_answers pakai
            // cascadeOnDelete, jadi hapus di sini bisa diam-diam menghapus riwayat kuis peserta.
            // Lihat guard yang sama di QuestionBankService::delete() (portal BankSoalPage).
            DeleteAction::make()
                ->visible(fn () => QuestionBanksResource::canDelete($this->record)
                    && ! $this->record->quizzes()->exists()
                    && ! $this->record->answers()->exists())
                ->before(function (DeleteAction $action) {
                    if ($this->record->quizzes()->exists() || $this->record->answers()->exists()) {
                        Notification::make()
                            ->title('Soal ini sudah digunakan di kuis dan tidak bisa dihapus.')
                            ->danger()
                            ->send();

                        $action->cancel();
                    }
                }),
        ];
    }
}
