<?php

namespace App\Filament\Resources\ManPower\JobOpenings\Schemas;

use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\ToggleButtons;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class JobOpeningForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components(
                $schema->getLivewire() instanceof ViewRecord
                    ? self::viewSchema()
                    : self::editSchema()
            )
            ->columns(2);
    }

    protected static function editSchema(): array
    {
        return [
            Section::make('Detail Lowongan Kerja')
                ->description('Informasi mengenai lowongan kerja yang dibuka')
                ->columns(2)
                ->columnSpanFull()
                ->schema([
                    TextEntry::make('company.name')
                        ->label('Perusahaan'),

                    TextEntry::make('jobPosition.name')
                        ->label('Posisi Jabatan'),

                    TextEntry::make('jobPosition.orgStructure.name')
                        ->label('Unit'),
                ]),

            Section::make()
                ->hiddenLabel()
                ->columnSpanFull()
                ->schema([
                    RichEditor::make('description')
                        ->label('Deskripsi Lowongan Kerja'),

                    RichEditor::make('requirements')
                        ->label('Persyaratan Lowongan Kerja'),

                    ToggleButtons::make('is_active')
                        ->label('Status Aktif')
                        ->options([
                            1 => 'Aktif',
                            0 => 'Tidak Aktif',
                        ])
                        ->inline()
                        ->default(1)
                        ->required(),
                ])
        ];
    }

    protected static function viewSchema(): array
    {
        return [
            Section::make(fn ($record) => "Lowongan Kerja - {$record->jobPosition->name} - {$record->jobPosition->orgStructure->name}")
                ->description('Informasi mengenai lowongan kerja')
                ->columns(2)
                ->columnSpanFull()
                ->schema([
                    TextEntry::make('jobPosition.name')
                        ->label('Posisi Jabatan'),

                    TextEntry::make('jobPosition.orgStructure.name')
                        ->label('Unit'),

                    TextEntry::make('quota')
                        ->label('Kuota'),

                    TextEntry::make('applicants_count')
                        ->label('Jumlah Pelamar')
                        ->counts('applicants'),

                    TextEntry::make('is_active')
                        ->label('Status')
                        ->formatStateUsing(fn ($state): string => $state ? 'Aktif' : 'Tidak Aktif'),
                ]),

            Section::make()
                ->hiddenLabel()
                ->columnSpanFull()
                ->schema([
                    TextEntry::make('description')
                        ->label('Deskripsi Lowongan Kerja')
                        ->html(),

                    TextEntry::make('requirements')
                        ->label('Persyaratan Lowongan Kerja')
                        ->html(),
                ])
        ];
    }
}
