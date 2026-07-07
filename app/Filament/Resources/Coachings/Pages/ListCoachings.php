<?php

namespace App\Filament\Resources\Coachings\Pages;

use App\Filament\Resources\Coachings\CoachingResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListCoachings extends ListRecords
{
    protected static string $resource = CoachingResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
