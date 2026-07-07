<?php

namespace App\Filament\Resources\Competency\PositionCompetencies\Schemas;

use Filament\Forms\Components\Select;
use Filament\Schemas\Components\Section as SchemaSection;
use Filament\Schemas\Schema;

class PositionCompetencyForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                SchemaSection::make('Informasi Jabatan & Kompetensi')
                    ->columnSpanFull()
                    ->description('Edit relasi antara jabatan dan kompetensi.')
                    ->schema([
                        Select::make('job_position_id')
                            ->label('Jabatan')
                            ->options(function () {
                                return \App\Models\Master\JobPositions::pluck('name', 'id')->toArray();
                            })
                            ->columnSpanFull()
                            ->searchable()
                            ->preload()
                            ->required()
                            ->helperText('Pilih jabatan untuk kompetensi ini'),

                        Select::make('competency_id')
                            ->label('Kompetensi')
                            ->options(function () {
                                return \App\Models\Master\Competencies::pluck('name', 'id')->toArray();
                            })
                            ->columnSpanFull()
                            ->searchable()
                            ->preload()
                            ->required()
                            ->helperText('Pilih kompetensi untuk jabatan ini'),
                    ])
                    ->columns(1)
            ]);
    }
}
