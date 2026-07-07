<?php

namespace App\Filament\Resources\Payroll\ExtraHourAllowances\Pages;

use App\Filament\Clusters\ShiftPattern\Widgets\AttendancePoliciesWidget;
use App\Filament\Resources\Payroll\ExtraHourAllowances\ExtraHourAllowancesResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListExtraHourAllowances extends ListRecords
{
    protected static string $resource = ExtraHourAllowancesResource::class;

    protected static ?string $title = 'Tunjangan Kelebihan Jam Kerja';

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('Tambah Tunjangan Kelebihan Jam Kerja'),
        ];
    }

    protected function getFooterWidgets(): array
    {
        return [
            AttendancePoliciesWidget::class,
        ];
    }
}
