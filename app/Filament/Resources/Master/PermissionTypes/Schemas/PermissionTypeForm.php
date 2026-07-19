<?php

namespace App\Filament\Resources\Master\PermissionTypes\Schemas;

use App\Enum\Attendance\PermissionTypeCategoryEnum;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Auth;

class PermissionTypeForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Hidden::make('company_id')
                    ->dehydrated()
                    ->default(fn () => Auth::user()->company_id),
                TextInput::make('name')
                    ->label('Nama Tipe Izin')
                    ->required()
                    ->columnSpanFull()
                    ->maxLength(255),

                Select::make('category')
                    ->label('Kategori')
                    ->options(collect(PermissionTypeCategoryEnum::cases())->mapWithKeys(fn ($c) => [$c->value => $c->label()]))
                    ->placeholder('Tidak ada kategori khusus')
                    ->helperText('Pilih kategori kalau jenis izin ini mau ikut kena aturan batas gabungan (setting `izin_max_per_periode`, belum ada UI-nya sendiri).')
                    ->columnSpanFull(),
            ]);
    }
}
