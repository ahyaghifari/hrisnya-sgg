<?php

namespace App\Filament\Resources\ExtraShiftAssignments\Pages;

use App\Filament\Resources\ExtraShiftAssignments\ExtraShiftAssignmentResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageExtraShiftAssignments extends ManageRecords
{
    protected static string $resource = ExtraShiftAssignmentResource::class;

    protected static ?string $title = 'Extra Shift';

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Tambah Extra Shift'),
        ];
    }
}
