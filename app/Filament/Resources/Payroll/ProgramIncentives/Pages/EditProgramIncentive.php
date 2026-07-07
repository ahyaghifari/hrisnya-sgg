<?php

namespace App\Filament\Resources\Payroll\ProgramIncentives\Pages;

use App\Filament\Resources\Payroll\ProgramIncentives\ProgramIncentiveResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditProgramIncentive extends EditRecord
{
    protected static string $resource = ProgramIncentiveResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }
}
