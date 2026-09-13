<?php

namespace App\Filament\Rekrutmen\Resources\OfflineTests\Pages;

use App\Filament\Rekrutmen\Resources\OfflineTests\OfflineTestResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageOfflineTests extends ManageRecords
{
    protected static string $resource = OfflineTestResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
