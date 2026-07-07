<?php

namespace App\Filament\Resources\Master\Religions\Pages;

use App\Filament\Resources\Master\Religions\ReligionsResource;
use Filament\Resources\Pages\CreateRecord;

class CreateReligions extends CreateRecord
{
    protected static string $resource = ReligionsResource::class;

    protected static ?string $title = "Tambah Agama";
}
