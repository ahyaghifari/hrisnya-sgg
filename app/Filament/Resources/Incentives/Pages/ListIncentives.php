<?php

namespace App\Filament\Resources\Incentives\Pages;

use App\Filament\Resources\Incentives\IncentiveResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListIncentives extends ListRecords
{
    protected static string $resource = IncentiveResource::class;

    protected static ?string $title = 'Insentif';

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
        ];
    }
}
