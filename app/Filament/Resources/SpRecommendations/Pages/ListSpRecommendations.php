<?php

namespace App\Filament\Resources\SpRecommendations\Pages;

use App\Filament\Resources\SpRecommendations\SpRecommendationResource;
use Filament\Resources\Pages\ListRecords;

class ListSpRecommendations extends ListRecords
{
    protected static string $resource = SpRecommendationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            //
        ];
    }
}
