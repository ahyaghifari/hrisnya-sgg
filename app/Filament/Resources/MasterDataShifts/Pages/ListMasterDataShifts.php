<?php

namespace App\Filament\Resources\MasterDataShifts\Pages;

use App\Filament\Resources\MasterDataShifts\MasterDataShiftResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListMasterDataShifts extends ListRecords
{
    protected static string $resource = MasterDataShiftResource::class;

    protected static ?string $title = 'Master Data Shift';

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Buat Shift Baru')
                ->modalHeading('Buat Shift Baru')
                ->visible(fn () => MasterDataShiftResource::canCreate()),
        ];
    }
}
