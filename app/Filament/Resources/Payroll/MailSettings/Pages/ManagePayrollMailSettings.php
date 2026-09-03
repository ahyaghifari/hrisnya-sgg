<?php

namespace App\Filament\Resources\Payroll\MailSettings\Pages;

use App\Filament\Resources\Payroll\MailSettings\PayrollMailSettingResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManagePayrollMailSettings extends ManageRecords
{
    protected static string $resource = PayrollMailSettingResource::class;

    protected static ?string $title = 'Email Slip Gaji';

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
