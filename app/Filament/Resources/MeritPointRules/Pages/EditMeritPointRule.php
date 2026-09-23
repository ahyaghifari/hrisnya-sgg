<?php

namespace App\Filament\Resources\MeritPointRules\Pages;

use App\Filament\Resources\MeritPointRules\MeritPointRuleResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditMeritPointRule extends EditRecord
{
    protected static string $resource = MeritPointRuleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
