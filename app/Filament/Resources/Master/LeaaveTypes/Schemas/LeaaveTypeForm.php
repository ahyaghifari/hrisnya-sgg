<?php

namespace App\Filament\Resources\Master\LeaaveTypes\Schemas;

use App\Enum\Attendance\LeaveTypeCategoryEnum;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Auth;

class LeaaveTypeForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Hidden::make('company_id')
                    ->dehydrated()
                    ->default(fn () => Auth::user()->company_id),

                TextInput::make('name')
                    ->label('Nama Tipe Cuti')
                    ->required()
                    ->columnSpanFull()
                    ->maxLength(255),

                Select::make('category')
                    ->label('Kategori')
                    ->options(collect(LeaveTypeCategoryEnum::cases())->mapWithKeys(fn ($c) => [$c->value => $c->label()]))
                    ->placeholder('Tidak ada kategori khusus')
                    ->helperText('Pilih "Cuti Tahunan" kalau jenis cuti ini mau kena aturan batas akumulasi per bulan (setting `cuti_tahunan_max_per_bulan`, belum ada UI-nya sendiri).')
                    ->columnSpanFull(),

                TextInput::make('quota_days')
                    ->label('Kuota (Hari)')
                    ->required()
                    ->numeric(),

                Select::make('reset_period')
                    ->label('Periode Reset')
                    ->options([
                        'yearly' => 'Tahunan',
                        'monthly' => 'Bulanan',
                        'none' => 'Tidak Ada',
                    ])
                    ->required(),
            ]);
    }
}
