<?php

namespace App\Filament\Resources\LMS\Trainers\Pages;

use App\Filament\Resources\LMS\Trainers\TrainersResource;
use Filament\Resources\Pages\CreateRecord;

class CreateTrainers extends CreateRecord
{
    protected static string $resource = TrainersResource::class;
    protected static ?string $title = 'Buat Pemateri';
}
