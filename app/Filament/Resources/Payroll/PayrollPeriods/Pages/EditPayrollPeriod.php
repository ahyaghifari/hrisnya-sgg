<?php

namespace App\Filament\Resources\Payroll\PayrollPeriods\Pages;

use App\Filament\Resources\Payroll\PayrollPeriods\PayrollPeriodResource;
use App\Models\Payroll\PayrollPeriod;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Exceptions\Halt;

class EditPayrollPeriod extends EditRecord
{
    protected static string $resource = PayrollPeriodResource::class;

    protected function mutateFormDataBeforeSave(array $data): array
    {
        if ($this->record->processed_at) {
            Notification::make()
                ->title('This payroll period has already been processed and cannot be edited.')
                ->danger()
                ->send();
            throw new Halt();
        }

        $exists = PayrollPeriod::query()
            ->where('company_id', $data['company_id'])
            ->where('id', '!=', $this->record->id)
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
