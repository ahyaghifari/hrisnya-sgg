<?php

namespace App\Filament\Resources\ExecutiveAllowances\Pages;

use App\Filament\Resources\ExecutiveAllowances\ExecutiveAllowanceResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListExecutiveAllowances extends ListRecords
{
    protected static string $resource = ExecutiveAllowanceResource::class;

    protected static ?string $title = 'Tunjangan Eksekutif';

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
