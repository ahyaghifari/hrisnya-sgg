<?php

namespace App\Filament\Resources\Master\Kelas\Pages;

use App\Filament\Resources\Master\Kelas\KelasResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditKelas extends EditRecord
{
    protected static string $resource = KelasResource::class;
    protected static ?string $title = 'Edit Kelas';

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()
                ->visible(fn ($record) => KelasResource::canDelete($record)),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
