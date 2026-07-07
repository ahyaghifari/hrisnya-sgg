<?php

namespace App\Filament\Clusters\ShiftPattern\Resources\ShiftSwapApprovals\Pages;

use App\Filament\Clusters\ShiftPattern\Resources\ShiftSwapApprovals\ShiftSwapApprovalResource;
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
