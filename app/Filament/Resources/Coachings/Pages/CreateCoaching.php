<?php

namespace App\Filament\Resources\Coachings\Pages;

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

        $data['company_id'] = $auth->company_id;
        $data['created_by'] = $auth->id;

        return $data;
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
