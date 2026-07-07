<?php

namespace App\Filament\Resources\Master\Banks\Pages;

use App\Filament\Resources\Master\Banks\BanksResource;
use Filament\Resources\Pages\CreateRecord;

class CreateBanks extends CreateRecord
{
    protected static string $resource = BanksResource::class;
    protected static ?string $title = 'Tambah Bank';
}
