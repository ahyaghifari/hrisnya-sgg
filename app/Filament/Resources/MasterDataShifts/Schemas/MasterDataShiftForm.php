<?php

namespace App\Filament\Resources\MasterDataShifts\Schemas;

use App\Enum\OrgStructurLevel;
use App\Enum\ShiftType;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Auth;

class MasterDataShiftForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Form Master Data Shift')
                    ->columnSpanFull()
                    ->columns(2)
                    ->schema([
                        Hidden::make('company_id')
                            ->default(Auth::user()->company_id),
                            
                        TextInput::make('code')
                            ->label('Kode Shift')
                            ->maxLength(50)
                            ->helperText('Opsional, bisa diisi untuk kode unik shift'),

                        TextInput::make('name')
                            ->label('Nama Shift')
                            ->required()
                            ->maxLength(255),
                        
                        Select::make('org_structure_id')
                            ->label('Divisi')
                            ->required()
                            ->options(function () {
                                $companyId = Auth::user()->company_id;
                                return \App\Models\Master\OrgStructure::where('company_id', $companyId)
                                    ->where('level', OrgStructurLevel::LEVEL_DIVISION->value)
                                    ->pluck('name', 'id');
                            })
                            ->searchable(),

                        TimePicker::make('start_time')
                            ->label('Waktu Mulai')
                            ->required(),

                        TimePicker::make('end_time')
                            ->label('Waktu Selesai')
                            ->required(),

                        Toggle::make('is_overnight')
                            ->label('Apakah Shift Malam?')
                            ->helperText('Aktifkan jika shift ini berlangsung melewati tengah malam')
                            ->inline(false)
                            ->default(false),

                        Select::make('shift_type')
                            ->label('Tipe Shift')
                            ->options(ShiftType::class)
                            ->helperText('Tipe shift digunakan untuk membedakan ketika tukar shift')
                            ->searchable()
                            ->preload()
                            ->required(),
                    ])
            ]);
    }
}
