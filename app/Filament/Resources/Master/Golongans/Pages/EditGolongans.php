<?php

namespace App\Filament\Resources\Master\Golongans\Pages;

use App\Filament\Resources\Master\Golongans\GolongansResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditGolongans extends EditRecord
{
    protected static string $resource = GolongansResource::class;
    protected static ?string $title = 'Edit Golongan';

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()
                ->visible(fn ($record) => GolongansResource::canDelete($record)),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
