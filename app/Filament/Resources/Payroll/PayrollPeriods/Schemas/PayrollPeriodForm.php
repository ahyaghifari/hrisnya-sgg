<?php

namespace App\Filament\Resources\Payroll\PayrollPeriods\Schemas;

use Filament\Forms;
use Filament\Schemas\Schema;

class PayrollPeriodForm
{
    
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Forms\Components\Select::make('company_id')
                    ->label('Perusahaan')
                    ->relationship('company', 'name')
                    ->preload()
                    ->required(),

                Forms\Components\DatePicker::make('start_date')
                    ->label('Dari Tanggal')
                    ->required(),

                Forms\Components\DatePicker::make('end_date')
                    ->label('Sampai Tanggal')
                    ->required()
                    ->afterOrEqual('start_date'),

                Forms\Components\Select::make('signer_id')
                    ->label('Penandatangan')
                    ->relationship('signer', 'name')
                    ->searchable()
                    ->preload()
                    ->helperText('Nama yang muncul di bagian "Dibuat oleh" slip gaji - terpisah dari siapa yang menekan Generate.'),
            ]);
    }
}
