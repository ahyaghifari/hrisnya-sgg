<?php

namespace App\Filament\Resources\Payroll\ProgramIncentives\Pages;

use App\Filament\Resources\Payroll\ProgramIncentives\ProgramIncentiveResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewProgramIncentive extends ViewRecord
{
    protected static string $resource = ProgramIncentiveResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
