<?php

namespace App\Filament\Resources\LMS\LmsCategories\Pages;

use App\Filament\Resources\LMS\LmsCategories\LmsCategoriesResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditLmsCategories extends EditRecord
{
    protected static string $resource = LmsCategoriesResource::class;
    protected static ?string $title = 'Edit Kategori LMS';

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()->visible(LmsCategoriesResource::canDelete($this->record)),
        ];
    }
}
