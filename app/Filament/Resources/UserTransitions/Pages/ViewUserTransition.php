<?php

namespace App\Filament\Resources\UserTransitions\Pages;

use App\Filament\Resources\UserTransitions\UserTransitionResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewUserTransition extends ViewRecord
{
    protected static string $resource = UserTransitionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
