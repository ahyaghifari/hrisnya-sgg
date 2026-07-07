<?php

namespace App\Filament\Resources\Payroll\DoctorPayrollPeriods\Pages;

use App\Filament\Resources\Payroll\DoctorPayrollPeriods\DoctorPayrollPeriodResource;
use App\Models\Payroll\DoctorPayrollPeriod;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Filament\Support\Exceptions\Halt;
use Illuminate\Validation\ValidationException;

class CreateDoctorPayrollPeriod extends CreateRecord
{
    protected static string $resource = DoctorPayrollPeriodResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $exists = DoctorPayrollPeriod::query()
            ->where('company_id', $data['company_id'])
            ->where(function ($q) use ($data) {
                $q->whereBetween('start_date', [$data['start_date'], $data['end_date']])
                    ->orWhereBetween('end_date', [$data['start_date'], $data['end_date']])
                    ->orWhere(function ($q) use ($data) {
                        $q->where('start_date', '<=', $data['start_date'])
                            ->where('end_date', '>=', $data['end_date']);
                    });
            })
            ->exists();

        if ($exists) {
            Notification::make()
                ->title('Doctor payroll period already exists in the selected date range.')
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
