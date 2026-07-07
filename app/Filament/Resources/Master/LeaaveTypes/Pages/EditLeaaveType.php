<?php

namespace App\Filament\Resources\Master\LeaaveTypes\Pages;

use App\Filament\Resources\Master\LeaaveTypes\LeaaveTypeResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditLeaaveType extends EditRecord
{
    protected static string $resource = LeaaveTypeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
