<?php

namespace App\Filament\Resources\SalaryComponentAssignments\Pages;

use App\Filament\Resources\SalaryComponentAssignments\SalaryComponentAssignmentResource;
use Filament\Resources\Pages\ViewRecord;

class ViewSalaryComponentAssignment extends ViewRecord
{
    protected static string $resource = SalaryComponentAssignmentResource::class;

    protected static ?string $title = 'Mapping Komponen Gaji';

    protected function getHeaderActions(): array
    {
        return [];
    }
}
