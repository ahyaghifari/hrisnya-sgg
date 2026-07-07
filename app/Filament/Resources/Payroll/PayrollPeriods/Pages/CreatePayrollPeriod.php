<?php

namespace App\Filament\Resources\Payroll\PayrollPeriods\Pages;

use App\Filament\Resources\Payroll\PayrollPeriods\PayrollPeriodResource;
use App\Models\Payroll\PayrollPeriod;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Filament\Support\Exceptions\Halt;

class CreatePayrollPeriod extends CreateRecord
{
    protected static string $resource = PayrollPeriodResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $exists = PayrollPeriod::query()
            ->where('company_id', $data['company_id'])
            ->where(function ($q) use ($data) {
                $q->whereBetween('start_date', [$data['start_date'], $data['end_date']])
                    ->orWhereBetween('end_date', [$data['start_date'], $data['end_date']]);
            })
            ->exists();

        if ($exists) {
            Notification::make()
                ->title('Payroll period already exists in the selected date range.')
                ->danger()
                ->send();
            throw new Halt();
        }

        return $data;
    }

    protected function getRedirectUrl(): string
    {
        return static::$resource::getUrl('index');
    }
}
