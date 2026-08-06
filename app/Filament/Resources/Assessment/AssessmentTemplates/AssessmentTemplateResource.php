<?php

namespace App\Filament\Resources\Assessment\AssessmentTemplates;

use App\Filament\Resources\Assessment\AssessmentTemplates\Pages\ManageAssessmentTemplates;
use App\Models\Assessment\AssessmentTemplates;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class AssessmentTemplateResource extends Resource
{
    protected static ?string $model = AssessmentTemplates::class;

    protected static string|\UnitEnum|null $navigationGroup = 'Penilaian Karyawan';

    protected static ?string $navigationLabel = 'Template Penilaian';

    protected static ?int $navigationSort = 2;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Template Penilaian')
                    ->columns(2)
                    ->columnSpanFull()
                    ->schema([
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
                            ->preload(),

                        Select::make('job_position_id')
                            ->label('Jabatan')
                            ->relationship('jobPosition', 'name')
                            ->required()
                            ->searchable()
                            ->preload(),

                        Select::make('is_active')
                            ->label('Status Aktif')
                            ->options([1 => 'Aktif', 0 => 'Tidak Aktif'])
                            ->default(1)
                            ->required(),

                        Textarea::make('description')
                            ->label('Deskripsi')
                            ->rows(3)
                            ->columnSpanFull()
                            ->maxLength(65535)
                            ->nullable(),
                    ]),

                Repeater::make('templateItems')
                    ->label('Indikator Penilaian')
                    ->relationship('templateItems')
                    ->columns(2)
                    ->columnSpanFull()
                    ->addActionLabel('Tambah Indikator')
                    ->itemLabel(fn (array $state): ?string => $state['name'] ?? null)
                    ->schema([
                        TextInput::make('name')
                            ->label('Pertanyaan / Indikator')
                            ->columnSpanFull()
                            ->required(),

                        TextInput::make('group_label')
                            ->label('Label Grup')
                            ->helperText('Contoh: Kepemimpinan, Komunikasi, Disiplin.')
                            ->nullable(),

                        TextInput::make('weight_percent')
                            ->label('Bobot (%)')
                            ->numeric()
                            ->step('0.01')
                            ->minValue(0)
                            ->maxValue(100)
                            ->nullable(),

                        TextInput::make('target_score')
                            ->label('Target Nilai')
                            ->numeric()
                            ->step('0.01')
                            ->minValue(0)
                            ->nullable(),

                        TextInput::make('max_score')
                            ->label('Skor Maksimal')
                            ->numeric()
                            ->step('1')
                            ->minValue(0)
                            ->default(5)
                            ->nullable(),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Nama Template')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('assessmentType.name')
                    ->label('Tipe Penilaian')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('jobPosition.name')
                    ->label('Jabatan')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('is_active')
                    ->label('Status')
                    ->badge()
                    ->color(fn (bool $state): string => $state ? 'success' : 'danger')
                    ->formatStateUsing(fn (bool $state): string => $state ? 'Aktif' : 'Tidak Aktif'),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                EditAction::make()
                    ->hiddenLabel(),

                DeleteAction::make()
                    ->hiddenLabel(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageAssessmentTemplates::route('/'),
        ];
    }
}
