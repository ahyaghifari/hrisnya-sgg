<?php

namespace App\Filament\Resources\Assessment\UserAssessments\Pages;

use App\Filament\Resources\Assessment\UserAssessments\UserAssessmentResource;
use App\Services\Assessment\AssessmentService;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Filament\Notifications\Notification;

class EditUserAssessment extends EditRecord
{
    protected static string $resource = UserAssessmentResource::class;

    protected static ?string $title = 'Penilaian Karyawan';

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        app(AssessmentService::class)->ensureAssessmentScoresExist($this->record);

        return $data;
    }

    protected function afterSave(): void
    {
        // Calculate assessment score after save
        $service = app(AssessmentService::class);
        $result = $service->calculateUserAssessmentScore($this->record->id);

        if ($result['success']) {
            Notification::make()
                ->success()
                ->title('Perhitungan Nilai Berhasil')
                ->body('Nilai akhir: ' . $result['final_score'] . ' - ' . $result['final_category'])
                ->send();
        } else {
            Notification::make()
                ->warning()
                ->title('Perhitungan Nilai Gagal')
                ->body($result['error'] ?? 'Terjadi kesalahan saat menghitung nilai')
                ->send();
        }
    }
}
