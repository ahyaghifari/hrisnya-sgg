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
        // Ensure assessment scores exist for all template items
        $this->ensureAssessmentScoresExist();
        
        return $data;
    }

    protected function ensureAssessmentScoresExist(): void
    {
        $assessmentUser = $this->record;
        $assessment = $assessmentUser->assessments;
        
        if (!$assessment || !$assessment->template) {
            return;
        }

        $templateItems = $assessment->template->templateItems()->orderBy('sort_order')->get();
        
        foreach ($templateItems as $templateItem) {
            \App\Models\Assessment\AssessmentScores::firstOrCreate(
                [
                    'assessment_user_id' => $assessmentUser->id,
                    'template_item_id' => $templateItem->id,
                ],
                [
                    'score' => null,
                    'weighted_score' => null,
                    'comment' => null,
                ]
            );
        }
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
