<?php

namespace App\Filament\Resources\Master\EmployeeTypes\Pages;

use App\Filament\Resources\Master\EmployeeTypes\EmployeeTypeResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditEmployeeType extends EditRecord
{
    protected static string $resource = EmployeeTypeResource::class;
    protected static ?string $title = 'Edit Tipe Karyawan';

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()
                ->visible(fn (): bool => EmployeeTypeResource::canDelete($this->record)),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
