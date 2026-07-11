<?php

namespace App\Filament\Resources\ShiftSwapApprovals\Pages;

use App\Filament\Resources\ShiftSwapApprovals\ShiftSwapApprovalResource;
use Filament\Resources\Pages\ListRecords;

class ListShiftSwapApprovals extends ListRecords
{
    protected static string $resource = ShiftSwapApprovalResource::class;

    protected static ?string $title = 'Persetujuan Tukar Shift';

    protected function getHeaderActions(): array
    {
        return [];
    }
}
