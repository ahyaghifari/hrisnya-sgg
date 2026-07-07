<?php

namespace App\Filament\Resources\LMS\LmsCategories\Pages;

use App\Filament\Resources\LMS\LmsCategories\LmsCategoriesResource;
use Filament\Resources\Pages\CreateRecord;

class CreateLmsCategories extends CreateRecord
{
    protected static string $resource = LmsCategoriesResource::class;
    protected static ?string $title = 'Buat Kategori LMS';
}
