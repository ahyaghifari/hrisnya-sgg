<?php

namespace App\Filament\Resources\LMS\LmsCategories\Pages;

use App\Filament\Resources\LMS\LmsCategories\LmsCategoriesResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageLmsCategories extends ManageRecords
{
    protected static string $resource = LmsCategoriesResource::class;

    protected static ?string $title = 'Kategori LMS';

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Buat Kategori LMS')
                ->visible(LmsCategoriesResource::canCreate()),
        ];
    }
}
