<?php

namespace App\Filament\Resources\Counselings\Pages;

use App\Filament\Resources\Counselings\CounselingResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditCounseling extends EditRecord
{
    protected static string $resource = CounselingResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
