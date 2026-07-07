<?php

namespace App\Filament\Resources\Payroll\OvertimeRates\Schemas;

use App\Helpers\PermissionHelper;
use App\Models\Master\Attendance\OvertimeTypes;
use App\Models\Master\Companies;
use App\Models\Shift\MasterShift;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Auth;

class OvertimeRatesForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                Section::make('Informasi Lembur')
                    ->columnSpanFull()
                    ->columns(2)
                    ->schema([
                        Hidden::make('company_id')
                            ->default(fn () => Auth::user()->company_id)
                            ->dehydrated(),

                        Select::make('company_id')
                            ->label('Perusahaan')
                            ->options(Companies::pluck('name', 'id'))
                            ->searchable()
                            ->preload()
                            ->required()
                            ->default(fn () => Auth::user()->company_id)
                            ->visible(fn () => PermissionHelper::hasGlobalRole(Auth::user()))
                            ->reactive()
                            ->columnSpanFull(),

                        Select::make('shift_id')
                            ->label('Shift')
                            ->options(MasterShift::query()->pluck('name', 'id'))
                            ->searchable()
                            ->required()
                            ->native(false),

                        Select::make('overtime_type_id')
                            ->label('Jenis Lembur')
                            ->options(OvertimeTypes::query()->pluck('name', 'id'))
                            ->searchable()
                            ->nullable()
                            ->native(false),

                        TextInput::make('maximum_overtime_hours')
                            ->label('Maksimal Jam Lembur')
                            ->helperText('Batas maksimum jam lembur yang diperbolehkan.')
                            ->numeric()
                            ->minValue(0)
                            ->nullable(),

                        TextInput::make('overtime_rate')
                            ->label('Tarif Lembur')
                            ->helperText('Tarif lembur per jam (Rupiah).')
                            ->numeric()
                            ->minValue(0)
                            ->nullable()
                            ->prefix('Rp'),
                    ]),
            ]);
    }
}
