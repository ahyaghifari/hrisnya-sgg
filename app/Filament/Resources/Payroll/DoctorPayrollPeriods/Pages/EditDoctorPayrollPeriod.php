<?php

namespace App\Filament\Resources\Payroll\DoctorPayrollPeriods\Pages;

use App\Filament\Resources\Payroll\DoctorPayrollPeriods\DoctorPayrollPeriodResource;
use App\Models\Payroll\DoctorPayrollPeriod;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Exceptions\Halt;

class EditDoctorPayrollPeriod extends EditRecord
{
    protected static string $resource = DoctorPayrollPeriodResource::class;

    protected function getHeaderActions(): array
    {
        return [
            //ViewAction::make(),
            //DeleteAction::make(),
        ];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        if ($this->record->processed_at) {
            throw Halt::make()
                ->message('This payroll period has already been processed and cannot be edited.');
        }

        $exists = DoctorPayrollPeriod::query()
            ->where('company_id', $data['company_id'])
            ->where('id', '!=', $this->record->id)
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
