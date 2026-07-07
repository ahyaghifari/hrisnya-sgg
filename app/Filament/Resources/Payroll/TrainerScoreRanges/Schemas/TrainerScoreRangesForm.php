<?php

namespace App\Filament\Resources\Payroll\TrainerScoreRanges\Schemas;

use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Auth;

class TrainerScoreRangesForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Form Skala Nilai Trainer')
                    ->columns(2)
                    ->columnSpanFull()
                    ->schema([
                        Hidden::make('company_id')
                            ->dehydrated()
                            ->default(fn () => Auth::user()->company_id),
                            
                        Select::make('type')
                            ->label('Tipe')
                            ->options([
                                'honor' => 'Honor',
                                'validity' => 'Plafon',
                            ])
                            ->columnSpanFull()
                            ->required()
                            ->reactive(),

                        TextInput::make('min_score')
                            ->label('Nilai Minimum')
                            ->numeric()
                            ->required(),

                        TextInput::make('max_score')
                            ->label('Nilai Maksimum')
                            ->numeric()
                            ->required(),

                        TextInput::make('honor_amount')
                            ->label('Jumlah Honor')
                            ->numeric()
                            ->columnSpanFull()
                            ->nullable()
                            ->visible(fn (callable $get) => $get('type') === 'honor'),

                        TextInput::make('validity_months')
                            ->label('Masa Berlaku (Bulan)')
                            ->numeric()
                            ->nullable()
                            ->columnSpanFull()
                            ->visible(fn (callable $get) => $get('type') === 'validity'),
                    ])
            ]);
    }
}
