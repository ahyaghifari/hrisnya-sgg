<?php

namespace App\Filament\Resources\Master\Religions\Pages;

use App\Filament\Resources\Master\Religions\ReligionsResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditReligions extends EditRecord
{
    protected static string $resource = ReligionsResource::class;

    protected static ?string $title = "Edit Agama";

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()
                ->visible(fn ($record) => ReligionsResource::canDelete($record)),
        ];
    }
}
