<?php

namespace App\Filament\Resources\SalaryComponentAssignments\Pages;

use App\Filament\Resources\SalaryComponentAssignments\SalaryComponentAssignmentResource;
use Filament\Resources\Pages\ListRecords;

class ListSalaryComponentAssignments extends ListRecords
{
    protected static string $resource = SalaryComponentAssignmentResource::class;
    protected static ?string $title = 'Penempatan Komponen Gaji';

    protected function getHeaderActions(): array
    {
        return [];
    }
}
