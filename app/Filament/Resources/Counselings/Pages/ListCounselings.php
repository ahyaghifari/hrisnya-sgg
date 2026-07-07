<?php

namespace App\Filament\Resources\Counselings\Pages;

use App\Filament\Resources\Counselings\CounselingResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListCounselings extends ListRecords
{
    protected static string $resource = CounselingResource::class;

    protected function getHeaderActions(): array
    {
        return [
            //CreateAction::make(),
        ];
    }
}
