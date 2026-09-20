<?php

namespace App\Filament\Resources\LMS\QuestionBanks\Schemas;

use App\Enum\QuestionType;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class QuestionBanksForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Form Untuk Membuat/Mengedit Bank Soal')
                    ->description('Isi form di bawah ini untuk membuat atau mengedit bank soal.')
                    ->columnSpanFull()
                    ->schema([
                        Select::make('category_id')
                            ->label('Kategori Soal')
                            ->relationship('category', 'name')
                            ->searchable()
                            ->preload()
                            ->required()
                            ->placeholder('Pilih kategori soal')
                            ->columnSpanFull()
                            ->createOptionForm([
                                Section::make('Form Kategori')
                                    ->schema([
                                        TextInput::make('name')
                                            ->label('Nama Kategori')
                                            ->required()
                                            ->maxLength(100),
                                    ])
                            ])
                            ->createOptionModalHeading('Tambah Kategori Baru'),

                        RichEditor::make('question')
                            ->label('Soal')
                            ->required()
                            ->columnSpanFull(),

                        Select::make('type')
                            ->label('Tipe Soal')
                            ->options(QuestionType::class)
                            ->disableOptionWhen(fn (string $value): bool => ! in_array($value, array_column(QuestionType::creatable(), 'value'), true))
                            ->required()
                            ->searchable()
                            ->preload()
                            ->live()
                            ->placeholder('Pilih tipe soal'),

                        Grid::make(2)
                            ->visible(fn (callable $get) => $get('type') === QuestionType::Essay)
                            ->schema([
                                TextInput::make('min_score')
                                    ->label('Skor Minimal')
                                    ->numeric()
                                    ->required()
                                    ->default(fn (callable $get) => $get('type') === QuestionType::MultipleChoice ? 0 : 0)
                                    ->minValue(0)
                                    ->maxValue(100)
                                    ->placeholder('Masukkan skor minimal'),

                                TextInput::make('max_score')
                                    ->label('Skor Maksimal')
                                    ->numeric()
                                    ->required()
                                    ->default(fn (callable $get) => $get('type') === QuestionType::MultipleChoice ? 0 : 0)
                                    ->minValue(0)
                                    ->maxValue(100)
                                    ->placeholder('Masukkan skor maksimal')
                            ]),

                Repeater::make('options')
                    ->relationship('options')
                    ->label('Opsi Jawaban')
                    ->visible(fn (callable $get) => $get('type') === QuestionType::MultipleChoice)
                    ->itemLabel('Jawaban')
                    ->addActionLabel('Tambah Opsi Jawaban')
                    ->minItems(2)
                    ->maxItems(10)
                    ->columns(1)
                    ->columnSpanFull()
                    ->schema([
                        Grid::make(5)
                            ->schema([
                                Textarea::make('option_text')
                                    ->label('Teks Opsi')
                                    ->required()
                                    ->columnSpan(4)
                                    ->rows(2)
                                    ->placeholder('Masukkan teks untuk opsi jawaban ini.')
                                    ->helperText('Masukan opsi jawaban tanpa menambahkan huruf a., b., c., dst.'),
        
                                Toggle::make('is_correct')
                                    ->label('Opsi Benar?')
                                    ->inline(false)
                                    ->required()
                                    ->default(false)
                                ]),
                            ])
                    ])
                    
            ]);
    }
}
