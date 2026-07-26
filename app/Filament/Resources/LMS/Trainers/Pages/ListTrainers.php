<?php

namespace App\Filament\Resources\LMS\Trainers\Pages;

use App\Filament\Resources\LMS\Trainers\TrainersResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListTrainers extends ListRecords
{
    protected static string $resource = TrainersResource::class;
    protected static ?string $title = 'Pemateri';

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('Buat Pemateri')->visible(TrainersResource::canCreate()),
        ];
    }
}
