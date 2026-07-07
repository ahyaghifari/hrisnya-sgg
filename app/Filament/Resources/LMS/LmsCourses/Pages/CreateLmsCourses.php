<?php

namespace App\Filament\Resources\LMS\LmsCourses\Pages;

use App\Enum\LsmCourseStatus;
use App\Filament\Resources\LMS\LmsCourses\LmsCoursesResource;
use App\Models\LMS\Trainer\TrainerAssessmentDetails;
use App\Services\LMS\TrainerAssessmentService;
use Filament\Actions\Action;
use Filament\Resources\Pages\CreateRecord;

class CreateLmsCourses extends CreateRecord
{
    protected static string $resource = LmsCoursesResource::class;
    protected static ?string $title = 'Buat Pelatihan LMS';

    public function getFormActions(): array
    {
        return [
            Action::make('saveAsDraft')
                ->label('Save as Draft')
                ->color('gray')
                ->action(function () {
                    $this->data['status'] = LsmCourseStatus::Draft->value;

                    if (isset($this->data['meetings']) && is_array($this->data['meetings'])) {
                        $this->data['meetings'] = array_filter(
                            $this->data['meetings'],
                            fn ($meeting) => !empty($meeting['title'])
                        );
                    }

                    if (isset($this->data['feedbacks']) && is_array($this->data['feedbacks'])) {
                        $this->data['feedbacks'] = array_filter(
                            $this->data['feedbacks'],
                            fn ($feedback) => !empty($feedback['comment']) || !empty($feedback['rating'])
                        );
                    }

                    $this->create();
                }),

            Action::make('saveAndPublish')
                ->label('Save & Publish')
                ->color('primary')
                ->action(function () {
                    $this->data['status'] = LsmCourseStatus::Published->value;

                    if (isset($this->data['meetings']) && is_array($this->data['meetings'])) {
                        $this->data['meetings'] = array_filter(
                            $this->data['meetings'],
                            fn ($meeting) => !empty($meeting['title'])
                        );
                    }

                    if (isset($this->data['feedbacks']) && is_array($this->data['feedbacks'])) {
                        $this->data['feedbacks'] = array_filter(
                            $this->data['feedbacks'],
                            fn ($feedback) => !empty($feedback['comment']) || !empty($feedback['rating'])
                        );
                    }

                    $this->create();
                }),
        ];
    }

    protected function afterCreate(): void
    {
        $service = app(TrainerAssessmentService::class);
        $service->generateTrainerAssessmentResult($this->record->id);
    }
}


