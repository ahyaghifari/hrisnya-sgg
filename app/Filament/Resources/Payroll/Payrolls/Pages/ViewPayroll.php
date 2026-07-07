<?php

namespace App\Filament\Resources\Payroll\Payrolls\Pages;

use App\Filament\Resources\Payroll\Payrolls\PayrollResource;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;

class ViewPayroll extends ViewRecord
{
    protected $listeners = ['refresh' => '$refresh'];
    protected static string $resource = PayrollResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('markAsPaid')
                ->label('Mark as Paid')
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->requiresConfirmation()
                ->modalHeading('Mark Payroll as Paid')
                ->modalDescription(
                    'This action will mark the payroll as paid. This action cannot be undone.'
                )
                ->visible(fn () => ! $this->record->is_paid)
                ->action(function ($livewire) {
                    $this->record->update([
                        'is_paid' => true,
                    ]);

                    $livewire->dispatch('refreshRelation');

                    Notification::make()
                        ->title('Payroll marked as paid')
                        ->success()
                        ->send();
                }),
        ];
    }
}
