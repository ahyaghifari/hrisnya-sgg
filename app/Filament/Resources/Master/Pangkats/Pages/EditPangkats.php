<?php

namespace App\Filament\Resources\Master\Pangkats\Pages;

use App\Filament\Resources\Master\Pangkats\PangkatsResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditPangkats extends EditRecord
{
    protected static string $resource = PangkatsResource::class;
    protected static ?string $title = 'Edit Pangkat';

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()
                ->visible(fn () => PangkatsResource::canDelete($this->record)),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
