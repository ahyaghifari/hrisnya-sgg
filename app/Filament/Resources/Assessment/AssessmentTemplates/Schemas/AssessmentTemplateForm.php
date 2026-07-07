<?php

namespace App\Filament\Resources\Assessment\AssessmentTemplates\Schemas;

use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Auth;

class AssessmentTemplateForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                self::detailTemplateSchema(),
            ]);
    }

    private static function detailTemplateSchema(): Section
    {
        return Section::make('Template Form Untuk Penilaian')
            ->columns(2)
            ->columnSpanFull()
            ->schema([
                Hidden::make('company_id')
                    ->default(function ($record) {
                        $companyId = $record?->company_id ?? Auth::user()->company_id;
                        return $companyId;
                    }),

                TextInput::make('name')
                    ->label('Nama Template')
                    ->required()
                    ->columnSpanFull()
                    ->maxLength(255),

                Select::make('type_id')
                    ->label('Tipe Penilaian')
                    ->relationship('assessmentType', 'name')
                    ->required()
                    ->searchable()
                    ->preload()
                    ->placeholder('Pilih Tipe Penilaian'),


                Select::make('is_active')
                    ->label('Status Aktif')
                    ->options([
                        1 => 'Aktif',
                        0 => 'Tidak Aktif',
                    ])
                    ->default(1)
                    ->required(),

                Textarea::make('description')
                    ->label('Deskripsi')
                    ->rows(3)
                    ->maxLength(65535)
                    ->columnSpanFull(),
            ]);
    }

    private static function templateItemsSchema(): Tab
    {
        return Tab::make('Template Items')
            ->icon('heroicon-o-document-text')
            ->schema([
                Repeater::make('templateItems')
                    ->hiddenLabel()
                    ->relationship('templateItems')
                    ->columns(2)
                    ->columnSpanFull()
                    ->addActionLabel('Tambah Item Template')
                    ->itemLabel('Item Template')
                    ->schema([
                        TextInput::make('name')
                            ->label('Pertanyaan / Indikator')
                            ->helperText('Isi pertanyaan utama, contoh: "Atasan memberi arahan dengan jelas".')
                            ->columnSpanFull()
                            ->required(),

                        Textarea::make('description')
                            ->label('Deskripsi / Keterangan')
                            ->rows(3)
                            ->columnSpanFull()
                            ->nullable(),

                        TextInput::make('group_label')
                            ->label('Label Group')
                            ->helperText('Digunakan untuk mengelompokkan indikator, misalnya: Kepemimpinan, Komunikasi, Disiplin.')
                            ->nullable()
                            ->maxLength(255),

                        TextInput::make('weight_percent')
                            ->label('Bobot (%)')
                            ->step('0.01')
                            ->helperText('Isi jika indikator ini adalah KPI berbobot. Kosongkan jika bukan KPI.')
                            ->numeric()
                            ->minValue(0)
                            ->maxValue(100)
                            ->nullable(),

                        TextInput::make('target_score')
                            ->label('Target Nilai')
                            ->step('0.01')
                            ->helperText('Target nilai ideal (opsional). Digunakan untuk KPI Berbobot.')
                            ->numeric()
                            ->minValue(0)
                            ->nullable(),

                        TextInput::make('max_score')
                            ->label('Skor Maksimal')
                            ->numeric()
                            ->step('1')
                            ->helperText('Berguna untuk skala 1–5 atau 1–4. Default = 5.')
                            ->minValue(0)
                            ->default(5)
                            ->nullable(),

                        Fieldset::make('Metadata Tambahan')
                            ->columnSpanFull()
                            ->schema([
                                Select::make('meta_type')
                                    ->label('Tipe Metadata')
                                    ->options([
                                        'none' => 'Tidak Ada (default)',
                                        'mcq' => 'Multiple Choice (MCQ)',
                                        'orientation' => 'Orientasi / Onboarding',
                                    ])
                                    ->default('none')
                                    ->reactive()
                                    ->helperText('Pilih tipe metadata untuk menyesuaikan field tambahan.'),
                            ])
                    ]),
            ]);
    }
}
