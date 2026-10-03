<?php

namespace App\Filament\Persuratan\Resources\Perusahaans\Pages;

use App\Filament\Persuratan\Resources\Perusahaans\PerusahaanResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManagePerusahaans extends ManageRecords
{
    protected static string $resource = PerusahaanResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
