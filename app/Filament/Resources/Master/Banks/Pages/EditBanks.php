<?php

namespace App\Filament\Resources\Master\Banks\Pages;

use App\Filament\Resources\Master\Banks\BanksResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditBanks extends EditRecord
{
    protected static string $resource = BanksResource::class;
    protected static ?string $title = 'Edit Bank';

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()
                ->visible(fn () => BanksResource::canDelete($this->record)),
        ];
    }
}
