<?php

namespace App\Filament\Resources\Master\OvertimeTypes\Schemas;

use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Repeater\TableColumn;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Auth;

class OvertimeTypeForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Hidden::make('company_id')
                    ->dehydrated()
                    ->default(fn () => Auth::user()->company_id),
                TextInput::make('name')
                    ->label('Nama Tipe Lembur')
                    ->required()
                    ->columnSpanFull()
                    ->maxLength(255),
                // Section::make('Detail Tipe Lembur')
                //     ->columnSpanFull()
                //     ->schema([
                //         TextInput::make('name')
                //             ->label('Nama Tipe Lembur')
                //             ->required()
                //             ->columnSpanFull()
                //             ->maxLength(255),

                //         Repeater::make('Aturan Lembur')
                //             ->hiddenLabel()
                //             ->relationship('overtimeRateRules')
                //             ->addActionLabel('Tambah Aturan Lembur')
                //             ->table([
                //                 TableColumn::make('Tipe'),
                //                 TableColumn::make('jam Lembur'),
                //                 TableColumn::make('Rate')
                //             ])
                //             ->schema([
                //                 Select::make('day_type')
                //                     ->label('Tipe Hari Lembur')
                //                     ->required()
                //                     ->options([
                //                         'weekday' => 'Hari Kerja',
                //                         'weekend' => 'Akhir Pekan',
                //                     ]),

                //                 TextInput::make('hour')
                //                     ->label('jam Lembur')
                //                     ->required()
                //                     ->numeric(),

                //                 TextInput::make('rate_multiplier')
                //                     ->label('Rate')
                //                     ->required()
                //                     ->numeric(),
                //             ])
                //     ])
            ]);
    }
}
