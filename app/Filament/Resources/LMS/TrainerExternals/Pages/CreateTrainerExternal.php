<?php

namespace App\Filament\Resources\LMS\TrainerExternals\Pages;

use App\Filament\Resources\LMS\TrainerExternals\TrainerExternalResource;
use Filament\Resources\Pages\CreateRecord;

class CreateTrainerExternal extends CreateRecord
{
    protected static string $resource = TrainerExternalResource::class;
    protected static ?string $title = 'Tambah Trainer Eksternal';
}
