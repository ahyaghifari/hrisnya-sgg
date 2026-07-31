<?php

namespace App\Filament\Resources\Master\PermissionTypes\Schemas;

use App\Enum\Attendance\PermissionGroupEnum;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
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
                    ->options(collect(PermissionGroupEnum::masterCategories())->mapWithKeys(fn ($c) => [$c->value => $c->label()]))
                    ->placeholder('Tidak ada kategori khusus')
                    ->helperText('Cuma dipakai untuk jenis izin di bawah kelompok Meninggalkan Jam Kerja (Terlambat/Pulang Cepat/Tengah Hari) & Izin Khusus - kelompok lain (Tidak Masuk Seharian, Sakit) tidak butuh master, kategorinya otomatis dari kelompok yang dipilih di form pengajuan.')
                    ->columnSpanFull(),

                Textarea::make('description')
                    ->label('Keterangan/Deskripsi')
                    ->helperText('Opsional - tampil ke pemohon di bawah dropdown jenis izin saat mereka memilih tipe ini di form pengajuan (mis. syarat, jumlah hari, dokumen pendukung).')
                    ->rows(3)
                    ->columnSpanFull(),
            ]);
    }
}
