<?php

namespace App\Filament\Resources\Master\LeaveTypes\Pages;

use App\Filament\Resources\Master\LeaveTypes\LeaveTypeResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageLeaveTypes extends ManageRecords
{
    protected static string $resource = LeaveTypeResource::class;

    protected static ?string $title = 'Jenis Cuti';

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Tambah Jenis Cuti'),
        ];
    }
}
