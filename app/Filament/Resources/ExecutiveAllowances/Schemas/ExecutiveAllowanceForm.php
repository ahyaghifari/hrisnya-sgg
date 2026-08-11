<?php

namespace App\Filament\Resources\ExecutiveAllowances\Schemas;

use App\Models\User;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class ExecutiveAllowanceForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('user_id')
                    ->label('Penerima')
                    ->options(fn () => User::withoutGlobalScope('non_karyawan')
                        ->whereIn('id', User::PAYROLL_ELIGIBLE_EXECUTIVE_IDS)
                        ->orderBy('name')
                        ->pluck('name', 'id'))
                    ->required(),

                Select::make('type')
                    ->label('Jenis')
                    ->options([
                        'earning' => 'Pendapatan',
                        'deduction' => 'Potongan',
                    ])
                    ->required(),

                TextInput::make('label')
                    ->label('Label')
                    ->required()
                    ->maxLength(255),

                TextInput::make('amount')
                    ->label('Nominal')
                    ->numeric()
                    ->prefix('Rp')
                    ->required(),

                Toggle::make('is_active')
                    ->label('Aktif')
                    ->default(true),

                Textarea::make('note')
                    ->label('Catatan')
                    ->rows(3)
                    ->nullable()
                    ->columnSpanFull(),
            ])->columns(2);
    }
}
