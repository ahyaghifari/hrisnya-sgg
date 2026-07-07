<?php

namespace App\Filament\Resources\Assessment\Assessments\Schemas;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\ToggleButtons;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Auth;

class AssessmentForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Master Data Penilaian')
                    ->columns(2)
                    ->icon(Heroicon::OutlinedDocumentCheck)
                    ->iconColor('black')
                    ->columnSpanFull()
                    ->schema([
                        Hidden::make('company_id')
                            ->default(function ($record) {
                                return $record?->company_id ?? Auth::user()->company_id;
                            }),

                        Fieldset::make('Informasi Penilaian')
                            ->schema([
                                TextInput::make('name')
                                    ->label('Nama Penilaian')
                                    ->required()
                                    ->columnSpanFull()
                                    ->placeholder('Masukkan Nama Penilaian'),
                                
                                Select::make('template_id')
                                    ->label('Template Penilaian')
                                    ->relationship('template', 'name')
                                    ->required()
                                    ->columnSpanFull()
                                    ->searchable()
                                    ->preload()
                                    ->placeholder('Pilih Template Penilaian'),

                                Select::make('job_position_id')
                                    ->label('Jabatan Spesifik')
                                    ->helperText('Opsional: Pilih jabatan spesifik jika penilaian ini ditujukan untuk jabatan tertentu.')
                                    ->relationship('jobPosition')
                                    ->options(function () {
                                        return \App\Models\Master\JobPositions::where('company_id', Auth::user()->company_id)
                                            ->pluck('name', 'id');
                                    })
                                    ->searchable()
                                    ->preload()
                                    ->nullable()
                                    ->columnSpanFull()
                                    ->placeholder('Pilih Posisi Pekerjaan'),
                            ]),

                        Fieldset::make('Jadwal Penilaian')
                            ->schema([
                                DatePicker::make('start_date')
                                    ->label('Tanggal Mulai')
                                    ->required()
                                    ->columnSpanFull()
                                    ->placeholder('Pilih Tanggal Mulai'),

                                DatePicker::make('end_date')
                                    ->label('Tanggal Selesai')
                                    ->required()
                                    ->columnSpanFull()
                                    ->placeholder('Pilih Tanggal Selesai'),

                                ToggleButtons::make('status')
                                    ->label('Status')
                                    ->options([
                                        'active' => 'Aktif',
                                        'inactive' => 'Tidak Aktif',
                                    ])
                                    ->colors([
                                        'active' => 'success',
                                        'inactive' => 'danger',
                                    ])
                                    ->inline()
                                    ->default('active')
                                    ->columnSpanFull()
                                    ->required(),
                            ]),
                    ])
            ]);
    }
}
