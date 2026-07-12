<?php

namespace App\Filament\Resources\SalaryComponents\Schemas;

use App\Enum\Salary\CalculationTypeEnum;
use App\Enum\Salary\SalaryComponentCategoryEnum;
use App\Models\Master\Companies;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class SalaryComponentForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label('Nama')
                    ->required()
                    ->maxLength(255)
                    ->columnSpanFull(),

                Select::make('category')
                    ->label('Kategori')
                    ->helperText('Dipakai mesin hitung payroll buat cari komponen ini otomatis.')
                    ->options(collect(SalaryComponentCategoryEnum::cases())->mapWithKeys(fn ($c) => [$c->value => $c->label()]))
                    ->required(),

                Select::make('company_id')
                    ->label('Perusahaan')
                    ->relationship('company', 'name')
                    ->searchable()
                    ->preload()
                    ->helperText('Kosongkan = berlaku untuk semua company (shared).'),

                Select::make('calculation_type')
                    ->label('Metode Perhitungan')
                    ->options([
                        CalculationTypeEnum::FIXED->value => 'Tetap',
                        CalculationTypeEnum::PERCENTAGE->value => 'Persentase',
                        CalculationTypeEnum::FORMULA->value => 'Formula',
                    ])
                    ->required()
                    ->live(),

                TextInput::make('default_value')
                    ->label('Nilai Default')
                    ->numeric(),

                TextInput::make('formula_key')
                    ->label('Kode Formula')
                    ->visible(fn (callable $get) => $get('calculation_type') === CalculationTypeEnum::FORMULA->value),

                Select::make('employeeTypes')
                    ->label('Tipe Karyawan Eligible')
                    ->relationship('employeeTypes', 'name')
                    ->multiple()
                    ->searchable()
                    ->preload()
                    ->helperText('Kosongkan = berlaku untuk semua tipe karyawan.')
                    ->columnSpanFull(),

                // Textarea::make('note')
                //     ->label('Catatan')
                //     ->rows(3)
                //     ->columnSpanFull(),

                Toggle::make('is_active')
                    ->label('Aktif')
                    ->default(true),
            ])
            ->columns(2);
    }
}
