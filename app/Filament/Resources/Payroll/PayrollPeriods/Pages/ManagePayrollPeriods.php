<?php

namespace App\Filament\Resources\Payroll\PayrollPeriods\Pages;

use App\Filament\Resources\Payroll\PayrollPeriods\PayrollPeriodResource;
use App\Models\Payroll\PayrollPeriod;
use Filament\Actions\CreateAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ManageRecords;
use Filament\Support\Exceptions\Halt;

class ManagePayrollPeriods extends ManageRecords
{
    protected static string $resource = PayrollPeriodResource::class;

    protected static ?string $title = 'Periode Penggajian';

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->using(function (array $data): PayrollPeriod {
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

                    return PayrollPeriod::create($data);
                }),
        ];
    }
}
