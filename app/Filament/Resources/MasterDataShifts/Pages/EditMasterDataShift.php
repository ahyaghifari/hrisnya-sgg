<?php

namespace App\Filament\Resources\MasterDataShifts\Pages;

use App\Filament\Resources\MasterDataShifts\MasterDataShiftResource;
use App\Filament\Resources\OvertimeCosts\OvertimeCostResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditMasterDataShift extends EditRecord
{
    protected static string $resource = MasterDataShiftResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()
                ->visible(fn ($record) => OvertimeCostResource::canDelete($record)),
        ];
    }
}
