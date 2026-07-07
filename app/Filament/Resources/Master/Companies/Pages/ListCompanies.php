<?php

namespace App\Filament\Resources\Master\Companies\Pages;

use App\Filament\Resources\Master\Companies\CompaniesResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Facades\Auth;

class ListCompanies extends ListRecords
{
    protected static string $resource = CompaniesResource::class;
    protected static ?string $title = "Daftar Perusahaan";

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('Buat Perusahaan Baru')->visible(fn () => CompaniesResource::canCreate()  && \App\Helpers\PermissionHelper::hasGlobalRole(Auth::user())),
        ];
    }
}
