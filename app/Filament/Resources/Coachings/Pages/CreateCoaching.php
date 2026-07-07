<?php

namespace App\Filament\Resources\Coachings\Pages;

use App\Enum\CoachingStatus;
use App\Filament\Resources\Coachings\CoachingResource;
use App\Filament\Resources\Coachings\Schemas\CoachingForm;
use Filament\Resources\Pages\CreateRecord;
use Filament\Schemas\Schema;

class CreateCoaching extends CreateRecord
{
    protected static string $resource = CoachingResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $auth = auth()->user();

        $data['status'] = CoachingStatus::IN_PROGRESS->value;
        $data['company_id'] = $auth->company_id;

        return $data;
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
