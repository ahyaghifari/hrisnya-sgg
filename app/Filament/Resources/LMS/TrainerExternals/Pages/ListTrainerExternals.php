<?php

namespace App\Filament\Resources\LMS\TrainerExternals\Pages;

use App\Filament\Resources\LMS\TrainerExternals\TrainerExternalResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListTrainerExternals extends ListRecords
{
    protected static string $resource = TrainerExternalResource::class;
    protected static ?string $title = 'Daftar Trainer Eksternal';

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('Tambah Trainer Eksternal'),
        ];
    }
}
