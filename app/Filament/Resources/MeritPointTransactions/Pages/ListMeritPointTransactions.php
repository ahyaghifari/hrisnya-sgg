<?php

namespace App\Filament\Resources\MeritPointTransactions\Pages;

use App\Filament\Resources\MeritPointTransactions\MeritPointTransactionResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListMeritPointTransactions extends ListRecords
{
    protected static string $resource = MeritPointTransactionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('Beri Merit Point'),
        ];
    }
}
