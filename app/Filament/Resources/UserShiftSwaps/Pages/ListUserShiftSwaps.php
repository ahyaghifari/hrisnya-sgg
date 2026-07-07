<?php

namespace App\Filament\Resources\UserShiftSwaps\Pages;

use App\Filament\Resources\UserShiftSwaps\UserShiftSwapResource;
use Filament\Actions\Action;
use Filament\Resources\Pages\ListRecords;

class ListUserShiftSwaps extends ListRecords
{
    protected static string $resource = UserShiftSwapResource::class;

    public function getTitle(): string
    {
        return 'Tukar Shift Saya';
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('create')
                ->label('Ajukan Tukar Shift')
                ->icon('heroicon-o-arrows-right-left')
                ->url(UserShiftSwapResource::getUrl('create'))
                ->visible(fn () => UserShiftSwapResource::canCreate()),
        ];
    }
}
