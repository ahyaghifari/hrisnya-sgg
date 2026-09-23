<?php

namespace App\Filament\Resources\MeritPointRules\Pages;

use App\Filament\Resources\MeritPointRules\MeritPointRuleResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListMeritPointRules extends ListRecords
{
    protected static string $resource = MeritPointRuleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
