<?php

namespace App\Filament\Resources\Assessment\AssessmentTemplates\RelationManagers;

use App\Enum\AssessmentTemplateItemType;
use Filament\Actions\AssociateAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\DissociateAction;
use Filament\Actions\DissociateBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\ToggleButtons;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Width;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;

class TemplateItemsRelationManager extends RelationManager
{
    protected static string $relationship = 'templateItems';

    protected static ?string $title = 'Form Item';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Form Pertanyaan / Pernyataan Untuk Template Penilaian')
                    ->columns(3)
                    ->columnSpanFull()
                    ->schema([
                        TextInput::make('name')
                            ->label('Pertanyaan / Indikator / Kompetensi')
                            ->helperText('Isi pertanyaan utama, contoh: "Atasan memberi arahan dengan jelas".')
                            ->columnSpanFull()
                            ->required(),

                        TextInput::make('group_label')
                            ->label('Label Grup')
                            ->helperText('Isi jika ingin mengelompokkan beberapa item dalam satu grup. Contoh: "Kepemimpinan", "Kedisiplinan".')
                            ->nullable()
                            ->columnSpanFull(),

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

                        ToggleButtons::make('metadata.additional_metadata')
                            ->label('Tambah Metadata')
                            ->helperText('Aktifkan jika ingin menambahkan metadata seperti pilihan jawaban, user PIC, dsb.')
                            ->options([
                                0 => 'Tidak',
                                1 => 'Ya',
                            ])
                            ->default(0)
                            ->inline()
                            ->reactive()
                            ->columnSpanFull(),

                        Fieldset::make('Metadata Tambahan')
                            ->columnSpanFull()
                            ->visible(fn (callable $get) => $get('metadata.additional_metadata') == 1)
                            ->schema([
                                Select::make('type')
                                    ->label('Tipe Metadata')
                                    ->options(AssessmentTemplateItemType::class)
                                    ->columnSpanFull()
                                    ->live()
                                    ->reactive(),

                                self::multipleQuestionForm(),
                                self::orientationForm(),
                            ]),

                    ]),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Pertanyaan / Indikator')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('weight_percent')
                    ->label('Bobot (%)')
                    ->alignCenter()
                    ->formatStateUsing(fn ($state) => $state !== null ? "{$state} %" : '-'),

                TextColumn::make('max_score')
                    ->alignCenter()
                    ->label('Skor Maksimal')
            ])
            ->filters([
                //
            ])
            ->headerActions([
                CreateAction::make()
                    ->slideOver()
                    ->modalHeading('Tambah Item Template')
                    ->label('Tambah Item'),
            ])
            ->recordActions([
                EditAction::make()
                    ->slideOver()
                    ->modalHeading('Edit Item Template'),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
    
    private static function multipleQuestionForm(): Repeater
    {
        return Repeater::make('metadata.options')
            ->label('Pilihan Jawaban (opsional)')
            ->belowLabel('Isi pilihan jawaban jika tipe metadata adalah Multiple Choice (MCQ).')
            ->visible(function (callable $get) {
                return $get('type') === AssessmentTemplateItemType::MCQ;
            })
            ->dehydrated()
            ->columnSpanFull()
            ->columns(4)
            ->addActionLabel('Tambah Pilihan Jawaban')
            ->schema([
                TextInput::make('value')
                    ->numeric()
                    ->columnSpan(1)
                    ->required()
                    ->label('Nilai')
                    ->helperText('Nilai skala dari jawaban ini, contoh: 1–5.'),

                TextInput::make('label')
                    ->required()
                    ->columnSpan(3)
                    ->label('Deskripsi Jawaban')
                    ->helperText('Isi teks jawaban, contoh: "Atasan memberi arahan yang jelas".'),
        ]);
    }

    private static function orientationForm()
    {
        return Section::make('Item Orientasi')
            ->description('Isi detail aktivitas satu per satu untuk item orientasi.')
            ->visible(function (callable $get) {
                return $get('type') === AssessmentTemplateItemType::ORIENTATION;
            })
            ->columnSpanFull()
            ->schema([
                Select::make('metadata.trainer_id')
                    ->label('Trainer / PIC')
                    ->columnSpanFull()
                    ->options(function () {
                        $companyId = Auth::user()->company_id;

                        if (!$companyId) {
                            return [];
                        }

                        return \App\Models\User::where('company_id', $companyId)
                            ->pluck('name', 'id');
                    }),

                RichEditor::make('metadata.activity')
                    ->label('Aktivitas')
                    ->columnSpanFull()
                    ->toolbarButtons([
                        'bold',
                        'italic',
                        'underline',
                        'bulletList',
                        'orderedList',
                        'link',
                    ])
                    ->required(),

                RichEditor::make('metadata.description')
                    ->label('Deskripsi Tambahan')
                    ->columnSpanFull()
                    ->toolbarButtons([
                        'bold',
                        'italic',
                        'underline',
                        'bulletList',
                        'orderedList',
                        'link',
                    ])
                    ->required(),
            ]);
    }
}
