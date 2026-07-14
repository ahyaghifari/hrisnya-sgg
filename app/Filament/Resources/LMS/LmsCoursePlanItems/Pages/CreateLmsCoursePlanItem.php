<?php

namespace App\Filament\Resources\LMS\LmsCoursePlanItems\Pages;

use App\Filament\Resources\LMS\LmsCoursePlanItems\LmsCoursePlanItemResource;
use App\Services\LMS\CourseService;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Auth;

class CreateLmsCoursePlanItem extends CreateRecord
{
    protected static string $resource = LmsCoursePlanItemResource::class;
    protected static ?string $title = 'Ajukan Pelatihan';

    public function getFormActions(): array
    {
        return [
            Action::make('saveAndSubmit')
                ->label('Submit')
                ->color('primary')
                ->requiresConfirmation()
                ->action(function () {
                    $state = $this->form->getState();
                    $state['request_status'] = 'submitted';
                    $this->record = $this->handleRecordCreation($state);
                    app(CourseService::class)->requestCourse(
                        coursePlanItem: $this->record,
                    );

                    Notification::make()
                        ->title('Pengajuan pelatihan berhasil dikirim')
                        ->success()
                        ->send();

                    $this->redirect(LmsCoursePlanItemResource::getUrl('index', ['type' => 'request']));
                }),

            Action::make('saveAsDraft')
                ->label('Save as Draft')
                ->color('gray')
                ->action(function () {
                    $state = $this->form->getState();
                    $state['request_status'] = 'draft';
                    $this->record = $this->handleRecordCreation($state);

                    Notification::make()
                        ->title('Pengajuan pelatihan berhasil disimpan sebagai draft')
                        ->success()
                        ->send();

                    $this->redirect(LmsCoursePlanItemResource::getUrl('index', ['type' => 'request']));
                }),

            Action::make('cancel')
                ->label('Cancel')
                ->color('gray')
                ->action(function () {
                    $this->redirect(LmsCoursePlanItemResource::getUrl('index', ['type' => 'request']));
                }),
        ];
    }
}
